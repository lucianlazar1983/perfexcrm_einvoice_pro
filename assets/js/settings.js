/**
 * Keeps E-Invoice RO note controls isolated from the rest of the Perfex settings page.
 */
(function einvoiceProSettings(window, document, $) {
    'use strict';

    var root;

    /**
     * Starts note management when the settings panel is present on the page.
     */
    function init() {
        root = document.getElementById('einvoice-pro-settings');
        if (!root || !$ || !window.csrfData) {
            return;
        }

        root.addEventListener('click', handlePanelClick);
    }

    /**
     * Routes panel clicks to the matching note action without inline handlers.
     */
    function handlePanelClick(event) {
        var toggleButton = event.target.closest('.toggle-manage-values');
        var addButton = event.target.closest('.add-new-value');
        var deleteButton = event.target.closest('.delete-value');

        if (toggleButton && root.contains(toggleButton)) {
            toggleValues(toggleButton);
            return;
        }

        if (addButton && root.contains(addButton)) {
            addValue(addButton);
            return;
        }

        if (deleteButton && root.contains(deleteButton)) {
            deleteValue(deleteButton);
        }
    }

    /**
     * Opens or closes one note manager while preserving its controls in the DOM.
     */
    function toggleValues(button) {
        var container = button.nextElementSibling;
        if (container) {
            container.hidden = !container.hidden;
        }
    }

    /**
     * Validates the visible input and asks the server to store its exact text.
     */
    function addValue(button) {
        var section = button.closest('.note-section');
        var input = section.querySelector('.new-value-input');
        var value = input.value.trim();

        if (!value) {
            return;
        }

        requestNote('add', section.dataset.index, value)
            .done(handleAddSuccess.bind(null, section, input, value))
            .fail(handleRequestFailure);
    }

    /**
     * Updates the list and selector only after the server confirms the addition.
     */
    function handleAddSuccess(section, input, value, response) {
        if (!response.success) {
            window.alert_float('warning', response.message);
            return;
        }

        section.querySelector('.custom-values-list').appendChild(createNoteRow(value));

        var select = section.querySelector('select');
        select.add(new window.Option(value, value));
        refreshSelect(select);

        input.value = '';
        window.alert_float('success', response.message);
    }

    /**
     * Confirms removal before sending the exact stored value to the server.
     */
    function deleteValue(button) {
        if (!window.confirm(root.dataset.confirmMessage)) {
            return;
        }

        var section = button.closest('.note-section');
        var row = button.closest('li');
        var value = button.dataset.value;

        requestNote('delete', section.dataset.index, value)
            .done(handleDeleteSuccess.bind(null, section, row, value))
            .fail(handleRequestFailure);
    }

    /**
     * Removes matching DOM and select entries only after a successful server response.
     */
    function handleDeleteSuccess(section, row, value, response) {
        if (!response.success) {
            window.alert_float('warning', response.message);
            return;
        }

        row.remove();

        var select = section.querySelector('select');
        var index;
        for (index = 0; index < select.options.length; index += 1) {
            if (select.options[index].value === value) {
                select.remove(index);
                break;
            }
        }
        refreshSelect(select);

        window.alert_float('success', response.message);
    }

    /**
     * Builds a note row with text nodes so custom content is never parsed as markup.
     */
    function createNoteRow(value) {
        var row = document.createElement('li');
        var text = document.createElement('span');
        var button = document.createElement('button');
        var icon = document.createElement('i');

        row.className = 'list-group-item einvoice-pro-note-row';
        text.className = 'einvoice-pro-note-text';
        text.textContent = value;

        button.type = 'button';
        button.className = 'btn btn-danger btn-xs delete-value';
        button.dataset.value = value;
        button.setAttribute('aria-label', root.dataset.deleteLabel || 'Delete');

        icon.className = 'fa fa-trash';
        icon.setAttribute('aria-hidden', 'true');

        button.appendChild(icon);
        row.appendChild(text);
        row.appendChild(button);

        return row;
    }

    /**
     * Sends one CSRF-protected JSON request through Perfex's existing jQuery runtime.
     */
    function requestNote(action, index, value) {
        var data = {note_value: value};
        data[window.csrfData.token_name] = window.csrfData.hash;

        return $.ajax({
            url: root.dataset.notesEndpoint + action + '/' + index,
            method: 'POST',
            data: data,
            dataType: 'json'
        });
    }

    /**
     * Keeps Perfex's enhanced select control in sync with its native element.
     */
    function refreshSelect(select) {
        if ($.fn.selectpicker) {
            $(select).selectpicker('refresh');
        }
    }

    /**
     * Shows a localized server error when available and a generic local fallback otherwise.
     */
    function handleRequestFailure(xhr) {
        var message = root.dataset.requestFailed;
        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }

        window.alert_float('danger', message);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document, window.jQuery));
