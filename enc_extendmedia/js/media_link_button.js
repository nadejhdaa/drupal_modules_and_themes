(function (CKEditor5) {
  const { Plugin } = CKEditor5.core;
  const { ButtonView } = CKEditor5.ui;

  class MediaLinkButton extends Plugin {
    static get pluginName() {
      return 'MediaLinkButton';
    }

    // Вспомогательный метод — теперь это метод класса, а не отдельная
    // функция в замыкании, так что проблем со scope быть не должно.
    _getSelectedDrupalMedia() {
      const selection = this.editor.model.document.selection;
      const selectedElement = selection.getSelectedElement();

      if (selectedElement && selectedElement.is('element', 'drupalMedia')) {
        return selectedElement;
      }
      return null;
    }

    init() {
      const editor = this.editor;

      editor.ui.componentFactory.add('mediaLinkButton', (locale) => {
        const view = new ButtonView(locale);

        view.set({
          label: 'Редактировать медиа',
          withText: true,
          tooltip: false,
        });

        view.bind('isEnabled').to(
          editor.model.document.selection,
          'isCollapsed',
          () => !!this._getSelectedDrupalMedia(),
        );

        view.on('execute', () => {
          const mediaElement = this._getSelectedDrupalMedia();

          if (!mediaElement) {
            // eslint-disable-next-line no-console
            console.warn('MediaLinkButton: drupalMedia не выделен.');
            return;
          }

          const uuid = mediaElement.getAttribute('drupalMediaEntityUuid');

          if (!uuid) {
            // eslint-disable-next-line no-console
            console.warn('MediaLinkButton: у элемента нет drupalMediaEntityUuid.', mediaElement);
            return;
          }

          const endpoint = Drupal.url(`enc-media/media/${uuid}/get-id`);
          view.set('isEnabled', false);

          fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin', // передать куки сессии
            headers: {
              Accept: 'application/json',
            },
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
            })
            .then((data) => {
              if (data && data.id) {
                window.open(Drupal.url(`media/${data.id}/edit`), '_blank');
              }
            })
            .catch((error) => {
              // eslint-disable-next-line no-console
              console.error('MediaLinkButton: не удалось получить ID медиа.', error);
            })
            .finally(() => {
              view.set('isEnabled', true);
            });
        });

        return view;
      });
    }
  }

  CKEditor5.enc_extendmedia = { MediaLinkButton };
})(CKEditor5);
