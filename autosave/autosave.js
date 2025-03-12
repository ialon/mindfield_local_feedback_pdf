/**
 * @copyright Mindfield Consulting
 */
var autosaveEvery = 2 * (60 * 1000);
var autosaveTimer = false;
var autosaveEndpoint = '/local/cpsopdf/autosave/autosave.php';

function cpsoDelete() {
    console.log('Autosave clearing responses');
    var params = {
        mode: 'delete',
        entityid: cpso_autosave_entityid
    };
    $.post(autosaveEndpoint, params, function (data) {
        // delete
    }, 'json');
}

function cpsoSerialize($frm) {
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

function cpsoPopulate($frm, data) {
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

function cpsoAutosave($frm, dosave) {
    if (dosave) {
        var params = {
            mode: 'save',
            entityid: cpso_autosave_entityid,
            data: cpsoSerialize($frm)
        };
        $.post(autosaveEndpoint, params, function (data) {
            console.log('Autosaved saving ', new Date());
            $('div.usermenu').after('<div id="cpsoAutosaveMsg" style="position:absolute;right:10px;top:55px;background-color:red;color:white;padding:5px">...autosave...</div>');
            setTimeout(function () { $('#cpsoAutosaveMsg').remove() }, 2 * 1000);
        }, 'json');
    }

    autosaveTimer = setTimeout(function () { cpsoAutosave($frm, true) }, autosaveEvery);
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
                    entityid: cpso_autosave_entityid
                };
                $.post(autosaveEndpoint, params, function (data) {
                    if (data.success) {
                        console.log('Autosave initialized');
                        if (confirm('Reload unsaved responses from ' + data.timecreated + '? If you select "Cancel", please be advised that data from your last session will not be saved.')) {
                            cpsoPopulate($frm, data.data);
                        } else {
                            cpsoDelete();
                        }
                    }

                    cpsoAutosave($frm, false);

                    $frm.submit(function () {
                        cpsoDelete();
                        if (autosaveTimer) clearTimeout(autosaveTimer);
                    });

                }, 'json');
            }

        });
    }
}
