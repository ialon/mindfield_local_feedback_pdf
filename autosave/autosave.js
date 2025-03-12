/**
 * @copyright Mindfield Consulting
 */
var autosaveEvery = 2 * (60 * 1000);
var autosaveTimer = false;
var autosaveEndpoint = '/local/feedback_pdf/autosave/autosave.php';

function feedbackpdfDelete() {
    console.log('Autosave clearing responses');
    var params = {
        mode: 'delete',
        entityid: feedbackpdf_autosave_entityid
    };
    $.post(autosaveEndpoint, params, function (data) {
        // delete
    }, 'json');
}

function feedbackpdfSerialize($frm) {
    var data = {};
    $('div.editor_atto_content', $frm).each(function () {
        data[$(this).attr('id')] = $(this).html();
    });
    $('fieldset input:checked', $frm).each(function () {
        data[$(this).attr('id')] = true;
    });
    $('input[type=text]', $frm).each(function () {
        data[this.name] = this.value;
    });
    $('select', $frm).each(function () {
        if (this.name.startsWith('field_') && !this.name.endsWith('_content1')) {
            data[this.name] = this.value;
        }
    });
    return data;
}

function feedbackpdfPopulate($frm, data) {
    console.log('Autosave restoring responses');
    $.each($(':input', $frm).get(), function () {
        $('div.editor_atto_content', $frm).each(function () {
            var id = $(this).attr('id');
            if (data.hasOwnProperty(id)) {
                $(this).html(data[id]);
                var id2 = id.replace('editable', '');
                $('textarea#'+id2).val(data[id]);
            } else {
                $(this).html('');
            }
        });
        $('fieldset input', $frm).each(function () {
            var id = $(this).attr('id');
            if (data.hasOwnProperty(id)) {
                $(this).attr('checked', true);
            } else {
                $(this).attr('checked', false);
            }
        });
        $('input[type=text]', $frm).each(function () {
            this.value = data[this.name];
        });
        $('select', $frm).each(function () {
            if (data.hasOwnProperty(this.name)) {
                this.value = data[this.name];
            }
        });
    });
}

function feedbackpdfAutosave($frm, dosave) {
    if (dosave) {
        var params = {
            mode: 'save',
            entityid: feedbackpdf_autosave_entityid,
            data: feedbackpdfSerialize($frm)
        };
        $.post(autosaveEndpoint, params, function (data) {
            console.log('Autosaved saving ', new Date());
            $('div.usermenu').after('<div id="feedbackpdfAutosaveMsg" style="position:absolute;right:10px;top:55px;background-color:red;color:white;padding:5px">...autosave...</div>');
            setTimeout(function () { $('#feedbackpdfAutosaveMsg').remove() }, 2 * 1000);
        }, 'json');
    }

    autosaveTimer = setTimeout(function () { feedbackpdfAutosave($frm, true) }, autosaveEvery);
}

document.onreadystatechange = function () {
    if (document.readyState == "complete") {
        require(['jquery'], function ($) {
            /**
             ** Main
             **/
            var $frm = $('form[action=edit\\.php]');

            if ($frm.length) {
                console.log('Autosave enabled');
                var params = {
                    mode: 'load',
                    entityid: feedbackpdf_autosave_entityid
                };
                $.post(autosaveEndpoint, params, function (data) {
                    if (data.success) {
                        console.log('Autosave initialized');
                        if (confirm('Reload unsaved responses from ' + data.timecreated + '? If you select "Cancel", please be advised that data from your last session will not be saved.')) {
                            feedbackpdfPopulate($frm, data.data);
                        } else {
                            feedbackpdfDelete();
                        }
                    }

                    feedbackpdfAutosave($frm, false);

                    $frm.submit(function () {
                        feedbackpdfDelete();
                        if (autosaveTimer) clearTimeout(autosaveTimer);
                    });

                }, 'json');
            }

        });
    }
}
