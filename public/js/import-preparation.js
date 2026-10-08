(function ($) {
    'use strict';
    $(function () {
        var config=window.TahmirImportPreparation;
        function prepare() {
            $('#tahmir-import-recovery').prop('hidden',true);
            window.TahmirMaterializeImportedPages(config,$('#tahmir-import-progress')).done(function (result) {
                if (!result.capture_failures && result.pages_imported[0].client_shell_materialized) {
                    window.location.replace(config.editor_url);
                } else {
                    $('#tahmir-import-progress').text(result.pages_imported[0].capture_error || 'The complete page could not be prepared yet.');
                    $('#tahmir-import-recovery').prop('hidden',false);
                }
            });
        }
        $('#tahmir-import-retry').on('click',prepare);
        prepare();
    });
}(jQuery));
