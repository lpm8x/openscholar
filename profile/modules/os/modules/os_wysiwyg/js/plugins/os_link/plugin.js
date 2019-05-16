/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, Drupal, drupalSettings, CKEDITOR) {

  var htmlContentPlaceholder = '[html content]';

  /**
   * Reads an anchor tag to determine whether it's internal, external, an e-mail or a link to a file
   * @param a
   * @return {link text, link url, link type}
   */
  function parseAnchor(a) {
    var text = '';
    if (a.innerHTML.startsWith('<')) {
      text = htmlContentPlaceholder;
    }
    else {
      text = a.innerHTML;
    }
    var ret = {
      text: text,
      url: '',
      title: '',
      is_blank: 0,
      type: ''
    };
    if (a.hasAttribute('data-fid')) {
      ret.url = a.getAttribute('data-fid');
      ret.type = 'media';
    }
    else if (a.origin == 'mailto://' || a.protocol == 'mailto:') {
      ret.email = a.pathname || a.href.replace('mailto:', '');
      ret.type = 'email';
    }
    else {
      var home = drupalSettings.path.baseUrl + (typeof drupalSettings.path.pathPrefix != 'undefined' ? drupalSettings.path.pathPrefix : ''),
        dummy = document.createElement('a');
      dummy.href = home;
      if (a.hasAttribute('data-url')) {
        ret.url = a.getAttribute('data-url');
        ret.type = 'web_address';
      }
      else {
        ret.url = a.href.replace(home, '');
        ret.type = 'web_address';
      }
    }
    ret.title = a.getAttribute('title');
    if (a.hasAttribute('target') && a.getAttribute('target') == '_blank') {
      ret.is_blank = 1;
    }
    return ret;
  }


  function getAttributes(editor, data) {
    var set = {};
    Object.keys(data || {}).forEach(function (attributeName) {
      set[attributeName] = data[attributeName];
    });
    set['data-cke-saved-href'] = set.href;

    var removed = {};
    Object.keys(set).forEach(function (s) {
      delete removed[s];
    });

    return {
      set: set,
      removed: CKEDITOR.tools.objectKeys(removed)
    };
  }

  function getSelectedLink(editor) {
    var selection = editor.getSelection();
    var selectedElement = selection.getSelectedElement();
    if (selectedElement && selectedElement.is('a')) {
      return selectedElement;
    }

    var range = selection.getRanges(true)[0];

    if (range) {
      range.shrink(CKEDITOR.SHRINK_TEXT);
      return editor.elementPath(range.getCommonAncestor()).contains('a', 1);
    }
    return null;
  }

  CKEDITOR.plugins.add('os_link', {
    icons: 'oslink,osunlink',
    hidpi: true,

    init: function init(editor) {
      editor.addCommand('os_link', {
        allowedContent: {
          a: {
            attributes: {
              '!href': true
            },
            classes: {}
          }
        },
        requiredContent: new CKEDITOR.style({
          element: 'a',
          attributes: {
            href: ''
          }
        }),
        modes: { wysiwyg: 1 },
        canUndo: true,
        exec: function exec(editor) {
          var drupalImageUtils = CKEDITOR.plugins.drupalimage;
          var focusedImageWidget = drupalImageUtils && drupalImageUtils.getFocusedWidget(editor);
          var linkElement = getSelectedLink(editor);

          var existingValues = {};
          if (linkElement && linkElement.$) {
            existingValues = parseAnchor(linkElement.$);
          } else if (focusedImageWidget && focusedImageWidget.data.link) {
            existingValues = CKEDITOR.tools.clone(focusedImageWidget.data.link);
          }
          if (existingValues.text == undefined) {
            if (editor.getSelection().getSelectedText()) {
              existingValues.text = editor.getSelection().getSelectedText();
            }
            else {
              existingValues.text = htmlContentPlaceholder;
            }
          }

          var saveCallback = function saveCallback(returnValues) {

            var newLinkData = {
              text: '',
              attributes: {},
              data: {},
            };
            newLinkData.attributes.title = returnValues.attributes.title;
            newLinkData.text = returnValues.attributes.text;

            // TODO: handle image widget.
            if (focusedImageWidget) {
              focusedImageWidget.setData('link', CKEDITOR.tools.extend(returnValues.attributes, focusedImageWidget.data.link));
              editor.fire('saveSnapshot');
              return;
            }

            editor.fire('saveSnapshot');

            // If Web Address is selected.
            if (returnValues.link_to.link_to__active_tab.indexOf("web-address") >= 0) {
              if (returnValues.web_address.href !== undefined) {
                newLinkData.attributes.href = returnValues.web_address.href;
                newLinkData.data.url = returnValues.web_address.href;
                if (returnValues.web_address.target_option == 1) {
                  newLinkData.attributes.target = '_blank';
                }
              }
            }
            // If Email is selected.
            if (returnValues.link_to.link_to__active_tab.indexOf("email") >= 0) {
              newLinkData.attributes.href = 'mailto:' + returnValues.email.email;
            }
            // Edit current link object.
            if (linkElement) {
              linkElement.removeAttribute('target');
              Object.keys(newLinkData.attributes || {}).forEach(function (attrName) {
                if (newLinkData.attributes[attrName].length > 0) {
                  var value = newLinkData.attributes[attrName];
                  linkElement.setAttribute(attrName, value);
                } else {
                  linkElement.removeAttribute(attrName);
                }
              });
              linkElement.removeAttribute('data-url');
              Object.keys(newLinkData.data || {}).forEach(function (dataName) {
                if (newLinkData.data[dataName].length > 0) {
                  var value = newLinkData.data[dataName];
                  linkElement.data(dataName, value);
                } else {
                  linkElement.data(dataName, null);
                }
              });
              linkElement.data('cke-saved-href', newLinkData.attributes.href);
              if (newLinkData.text != htmlContentPlaceholder) {
                linkElement.setHtml(newLinkData.text);
              }
            }
            // Create new link object.
            else if (returnValues.attributes.text) {
              var selection = editor.getSelection();
              var range = selection.getRanges(1)[0];

              // If selection is empty.
              if (range.collapsed) {
                var text = new CKEDITOR.dom.text(newLinkData.text, editor.document);
                range.insertNode(text);
                range.selectNodeContents(text);
              }

              // TODO: apply new data attributes as well
              var style = new CKEDITOR.style({
                element: 'a',
                attributes: newLinkData.attributes
              });
              style.type = CKEDITOR.STYLE_INLINE;
              style.applyToRange(range);
              range.select();

              linkElement = getSelectedLink(editor);
            }

            editor.fire('saveSnapshot');
          };

          var dialogSettings = {
            title: linkElement ? editor.config.osLink_dialogLinkEdit : editor.config.osLink_dialogLinkAdd,
            dialogClass: 'os-wysiwyg-link-dialog'
          };

          Drupal.ckeditor.openDialog(editor, Drupal.url('os_wysiwyg/dialog/os_link/' + editor.config.drupal.format), existingValues, saveCallback, dialogSettings);
        }
      });
      editor.addCommand('os_unlink', {
        contextSensitive: 1,
        startDisabled: 1,
        requiredContent: new CKEDITOR.style({
          element: 'a',
          attributes: {
            href: ''
          }
        }),
        exec: function exec(editor) {
          var style = new CKEDITOR.style({
            element: 'a',
            type: CKEDITOR.STYLE_INLINE,
            alwaysRemoveElement: 1
          });
          editor.removeStyle(style);
        },
        refresh: function refresh(editor, path) {
          var element = path.lastElement && path.lastElement.getAscendant('a', true);
          if (element && element.getName() === 'a' && element.getAttribute('href') && element.getChildCount()) {
            this.setState(CKEDITOR.TRISTATE_OFF);
          } else {
            this.setState(CKEDITOR.TRISTATE_DISABLED);
          }
        }
      });

      editor.setKeystroke(CKEDITOR.CTRL + 75, 'os_link');

      if (editor.ui.addButton) {
        editor.ui.addButton('OsLink', {
          label: Drupal.t('Link'),
          command: 'os_link'
        });
        editor.ui.addButton('OsUnlink', {
          label: Drupal.t('Unlink'),
          command: 'os_unlink'
        });
      }

      editor.on('doubleclick', function (evt) {
        var element = getSelectedLink(editor) || evt.data.element;

        if (!element.isReadOnly()) {
          if (element.is('a')) {
            editor.getSelection().selectElement(element);
            editor.getCommand('os_link').exec();
          }
        }
      });

      if (editor.addMenuItems) {
        editor.addMenuItems({
          os_link: {
            label: Drupal.t('Edit Link'),
            command: 'os_link',
            group: 'link',
            order: 1
          },

          os_unlink: {
            label: Drupal.t('Unlink'),
            command: 'os_unlink',
            group: 'link',
            order: 5
          }
        });
      }

      if (editor.contextMenu) {
        editor.contextMenu.addListener(function (element, selection) {
          if (!element || element.isReadOnly()) {
            return null;
          }
          var anchor = getSelectedLink(editor);
          if (!anchor) {
            return null;
          }

          var menu = {};
          if (anchor.getAttribute('href') && anchor.getChildCount()) {
            menu = {
              os_link: CKEDITOR.TRISTATE_OFF,
              os_unlink: CKEDITOR.TRISTATE_OFF
            };
          }
          return menu;
        });
      }
    }
  });

  CKEDITOR.plugins.os_link = {
    getLinkAttributes: getAttributes
  };
})(jQuery, Drupal, drupalSettings, CKEDITOR);
