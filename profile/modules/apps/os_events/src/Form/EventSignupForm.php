<?php

namespace Drupal\os_events\Form;

use DateInterval;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\os_events\MailNotificationsInterface;
use Drupal\rng\Entity\Registrant;
use Drupal\rng\Entity\Registration;
use Drupal\rng\EventManagerInterface;
use Drupal\rng\RegistrantFactoryInterface;
use Drupal\rng_contact\Entity\RngContact;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the ModalForm for Signup.
 */
class EventSignupForm extends FormBase {

  /**
   * Constructs EventSignup object.
   *
   * @param \Drupal\rng\RegistrantFactoryInterface $registrantFactory
   *   The registrant factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The EntityManager service.
   * @param \Drupal\rng\EventManagerInterface $eventManager
   *   The Event Manager service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The Messenger service.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   The Date formatter service.
   * @param \Drupal\os_events\MailNotificationsInterface $mailNotification
   *   The mail notification service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   */
  public function __construct(RegistrantFactoryInterface $registrantFactory, EntityTypeManagerInterface $entityManager, EventManagerInterface $eventManager, Messenger $messenger, DateFormatter $dateFormatter, MailNotificationsInterface $mailNotification, Connection $database) {
    $this->registrantFactory = $registrantFactory;
    $this->entityManager = $entityManager;
    $this->eventManager = $eventManager;
    $this->messenger = $messenger;
    $this->dateFormatter = $dateFormatter;
    $this->mailNotification = $mailNotification;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rng.registrant.factory'),
      $container->get('entity_type.manager'),
      $container->get('rng.event_manager'),
      $container->get('messenger'),
      $container->get('date.formatter'),
      $container->get('os_events.mail_notifications'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'events_signup_modal_form';
  }

  /**
   * Helper method so we can have consistent dialog options.
   *
   * @return string[]
   *   An array of jQuery UI elements to pass on to our dialog form.
   */
  protected static function getDataDialogOptions() {
    return [
      'width' => '50%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL, $timestamp = NULL) {

    // Add the core AJAX library.
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#prefix'] = '<div id = "signup-modal-form">';
    $form['#suffix'] = '</div>';

    $dateTimeObject = DrupalDateTime::createFromTimestamp($timestamp);
    $offset = $dateTimeObject->getOffset();
    $interval = DateInterval::createFromDateString((string) $offset . 'seconds');
    $dateTimeObject->add($interval);
    $dateDisplay = $dateTimeObject->format('l, F j, Y H:i:s');

    $form['field_repeating_event_date_text'] = [
      '#markup' => '<span class="date-display-single">' . $this->t('On @date', ['@date' => $dateDisplay]) . "</span>",
      '#weight' => -10,
    ];

    $dateStorage = $this->dateFormatter->format($timestamp, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $form['registering_for_date'] = [
      '#type' => 'hidden',
      '#value' => $dateStorage,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#size' => 40,
    ];

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
      '#size' => 40,
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#size' => 40,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Signup'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];
    $form['#cache'] = ['max-age' => 0];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $node = $this->entityManager->getStorage('node')->load($form_state->getValue('nid'));
    $eventMeta = $this->eventManager->getMeta($node);
    $registrations = $eventMeta->getRegistrations();
    $emailEntered = $form_state->getValue('email');
    $forDate = $form_state->getValue('registering_for_date');

    foreach ($registrations as $registration) {
      $ids[] = $registration->id();
    }

    // To find out if a email is already registered for a particular date.
    $query = $this->database
      ->select('registration__field_for_date', 'rfd')
      ->fields('rfd', []);
    $query->join('registrant', 'reg', 'rfd.entity_id = reg.registration');
    $query->join('rng_contact__field_email', 'rce', 'reg.identity__target_id = rce.entity_id');
    $query->condition('rfd.entity_id', $ids, 'IN');
    $query->condition('rfd.field_for_date_value', $forDate);
    $query->condition('rce.field_email_value', $emailEntered);
    $result = $query->execute()->fetchAssoc();

    if ($result) {
      $form_state->setErrorByName('email', $this->t('User is already registered for this date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Implements the submit handler for the modal dialog AJAX call.
   *
   * @param array $form
   *   Render array representing from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Array of AJAX commands to execute on submit of the modal form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rng\Exception\InvalidEventException
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($form_state->getErrors()) {
      $response->addCommand(new ReplaceCommand('#signup-modal-form', $form));
      $this->messenger()->deleteAll();
    }

    else {
      $values = $form_state->getValues();
      $node = $this->entityManager->getStorage('node')->load($form_state->getValue('nid'));

      // Create registration and registrant.
      $this->createRegistration($values, $node);

      $eventMeta = $this->eventManager->getMeta($node);

      // Check if capacity is full,replace Signup with relevant message.
      $capacity = $eventMeta->remainingCapacity();
      $slot_available = FALSE;
      if ($capacity == -1 || $capacity > 0) {
        $slot_available = TRUE;
      }
      if (!$slot_available) {
        $id = 'registration-link-' . $node->id();
        $message = '<div id="' . $id . '">' . $this->t("Sorry, the event is full") . '</div>';
        $response->addCommand(new ReplaceCommand('#' . $id, $message));
        $this->mailNotification->sendEventFullEmail($node);
      }
      $response->addCommand(new CloseModalDialogCommand());
    }
    // Finally return our response.
    return $response;
  }

  /**
   * Creates registrations, registrants and identities.
   *
   * @param array $values
   *   Form values entered vy the user.
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createRegistration(array $values, EntityInterface $node) {
    $date = $values['registering_for_date'];

    $registration = Registration::create([
      'type' => 'signup',
      'event' => $node,
      'field_for_date' => ['value' => $date],
    ]);
    $registration->save();

    $identity = RngContact::create([
      'type' => 'anonymous_',
      'label' => $values['full_name'],
      'field_email' => $values['email'],
      'field_department' => $values['department'],
    ]);
    $identity->save();

    $registrant = Registrant::create([
      'type' => 'registrant',
      'registration' => $registration,
      'identity' => $identity,
    ]);
    $registrant->save();
  }

}
