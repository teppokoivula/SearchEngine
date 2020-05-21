$(function() {

    // find debug containers
    const $debugContainers = $('.search-engine-debug');

    if ($debugContainers.length) {

        // define base url for debug requests
        const debugURL = ProcessWire.config.urls.admin + 'module/edit?name=SearchEngine&se-debug=1&';

        $debugContainers.each(function() {

            // debug container
            const $debugContainer = $(this);

            // add debug button
            const $button = $('<button>')
                .attr('class', 'ui-button ui-state-disabled')
                .attr('disabled', 'disabled')
                .insertBefore($debugContainer);
            const $buttonText = $('<span>')
                .text($debugContainer.data('debug-button-label'))
                .appendTo($button);
            const $buttonIcon = $('<i>')
                .addClass('fa fa-bug')
                .css('margin-left', '.5rem')
                .appendTo($button);

            if ($debugContainer.data('type') === 'page') {

                // get debug page ID
                let debugPageID = parseInt($debugContainer.data('page-id'));
                if (debugPageID) {
                    $button
                        .removeAttr('disabled')
                        .attr('class', 'ui-button ui-state-default');
                }

                // listen to page select event
                $("#Inputfield_debugger_page").on("pageSelected", function(e, data) {
                    $debugContainer
                        .html('')
                        .css('margin-top', 0);
                    $buttonText.text($debugContainer.data('debug-button-label'));
                    $buttonIcon.attr('class', 'fa fa-bug');
                    debugPageID = data.id;
                    if (debugPageID) {
                        $button
                            .removeAttr('disabled')
                            .attr('class', 'ui-button ui-state-default');
                    } else {
                        $button
                            .attr('disabled', 'disabled')
                            .attr('class', 'ui-button ui-state-disabled');
                    }
                });

                // listen to button click event
                $button.on("click", function(e) {
                    console.log(e);
                    e.preventDefault();
                    $button
                        .attr('disabled', 'disabled')
                        .attr('class', 'ui-button ui-state-disabled');
                    $buttonIcon.addClass('fa-spin');
                    $debugContainer.load(debugURL + 'se-debug-page-id=' + debugPageID, function() {
                        $buttonText.text($debugContainer.data('refresh-button-label'));
                        $buttonIcon.attr('class', 'fa fa-refresh');
                        $button
                            .removeAttr('disabled')
                            .attr('class', 'ui-button ui-state-default');
                        $debugContainer
                            .css('margin-top', '2rem')
                            .effect("highlight", {}, 1000);
                    });
                });

            } else if ($debugContainer.data('type') == 'query') {

                // get debug query
                let debugQuery = $debugContainer.data('query');
                if (debugQuery) {
                    $button
                        .removeAttr('disabled')
                        .attr('class', 'ui-button ui-state-default');
                }

                // listen to keyup event
                $('#Inputfield_debugger_query').on("keyup", function(e) {
                    if (e.key == 'Enter') {
                        e.preventDefault();
                    }
                    debugQuery = $(this).val();
                    if (debugQuery) {
                        $button
                            .removeAttr('disabled')
                            .attr('class', 'ui-button ui-state-default');
                        $buttonText
                            .text($debugContainer.data((debugQuery == prevQuery ? 'refresh' : 'debug') + '-button-label'));
                        if (e.key == 'Enter') {
                            $button.trigger('click');
                        }
                    } else {
                        $button
                            .attr('disabled', 'disabled')
                            .attr('class', 'ui-button ui-state-disabled');
                        $buttonText
                            .text($debugContainer.data('debug-button-label'));
                    }
                });

                // listen to button click event
                let prevQuery = debugQuery;
                $button.on("click", function(e) {
                    e.preventDefault();
                    $button
                        .attr('disabled', 'disabled')
                        .attr('class', 'ui-button ui-state-disabled');
                    $buttonIcon.addClass('fa-spin');
                    $debugContainer.load(debugURL + 'se-debug-query=' + encodeURIComponent(debugQuery), function() {
                        prevQuery = debugQuery;
                        $buttonText.text($debugContainer.data('refresh-button-label'));
                        $buttonIcon.attr('class', 'fa fa-refresh');
                        $button
                            .removeAttr('disabled')
                            .attr('class', 'ui-button ui-state-default');
                        $debugContainer
                            .css('margin-top', '2rem')
                            .effect("highlight", {}, 1000);
                    });
                });

            }

        });
    }
})
