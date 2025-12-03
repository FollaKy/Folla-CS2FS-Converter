(function($) {
    const config = window.csfcImportLocal || {};
    const menuSelector = config.menuSelector || '.fsnip_menu';
    const strings = config.strings || {};
    const ajaxUrl = config.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const nonce = config.nonce || (window.fluentSnippetAdmin ? window.fluentSnippetAdmin.nonce : '');

    const buttonText = strings.buttonText || 'Import local (CS2FS Converter)';
    const importingText = strings.importingText || 'Importing…';
    const successWithCount = strings.successWithCount || 'Import completed. Added %d snippet(s).';
    const successGeneric = strings.successGeneric || 'Import finished.';
    const failureText = strings.failure || 'Import failed.';

    const injectButton = function() {
        const $menu = $(menuSelector);
        if (!$menu.length || $menu.find('.csfc-import-local').length) {
            return false;
        }

        const $icon = $('<span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>');
        const $label = $('<span class="csfc-import-label"></span>').text(buttonText);
        const $button = $('<button type="button" class="button button-secondary csfc-import-local"></button>').append($icon).append($label);
        const $wrapper = $('<li class="csfc-import-local-item"></li>').append($button);
        $menu.append($wrapper);

        $button.on('click', function() {
            const $self = $(this);
            $self.prop('disabled', true).text(importingText);

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'fluent_snippets_import_local',
                    _nonce: nonce
                }
            }).done(function(resp) {
                console.log('CS2FS import response:', resp);
                const skipped = (resp && resp.skipped) ? resp.skipped : [];
                if (resp && resp.snippets) {
                    const count = parseInt(resp.snippets.length, 10) || 0;
                    let msg = successWithCount.replace('%d', count);
                    if (skipped.length) {
                        const details = skipped.slice(0, 5).map(function(item) {
                            const name = item.name || '(no name)';
                            const reason = item.reason || 'unknown';
                            const extra = item.message ? ' - ' + item.message : '';
                            return name + ': ' + reason + extra;
                        }).join('\n');
                        msg += '\nSkipped: ' + skipped.length + '\n' + details;
                    }
                    alert(msg);
                    window.location.reload();
                    return;
                }

                if (resp && resp.data && resp.data.message) {
                    alert(resp.data.message);
                    return;
                }

                alert(successGeneric);
            }).fail(function(xhr) {
                const resp = xhr.responseJSON || {};
                const message = (resp.data && resp.data.message) ? resp.data.message : failureText;
                alert(message);
            }).always(function() {
                $self.prop('disabled', false).text(buttonText);
            });

            return true;
        });

        return true;
    };

    const startObserver = function() {
        if (typeof MutationObserver === 'undefined') {
            return false;
        }

        const observer = new MutationObserver(function() {
            if (injectButton()) {
                observer.disconnect();
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
        return true;
    };

    $(function() {
        if (injectButton()) {
            return;
        }

        if (startObserver()) {
            // Observer will handle insertion when menu appears.
            return;
        }

        // Fallback: poll for a short period.
        let attempts = 0;
        const poll = setInterval(function() {
            attempts++;
            if (injectButton() || attempts >= 20) {
                clearInterval(poll);
            }
        }, 300);
    });
})(jQuery);
