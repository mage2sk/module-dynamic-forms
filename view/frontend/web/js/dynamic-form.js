define([
    'jquery',
    'mage/translate',
    'jquery/ui',
    'mage/validation'
], function ($, $t) {
    'use strict';

    $.widget('panth.dynamicForm', {
        options: {
            config: {},
            fields: [],
            selectors: {
                form: '.panth-df-form',
                successMessage: '.panth-df-success',
                successText: '.panth-df-success-text',
                globalError: '.panth-df-global-error',
                globalErrorText: '.panth-df-global-error-text',
                submitBtn: '.action.submit',
                btnText: '.panth-df-btn-text',
                btnLoading: '.panth-df-btn-loading',
                contentBelow: '.panth-df-content-below',
                fieldError: '[data-role="field-error"]',
                fileUpload: '.panth-df-file-upload',
                dropZone: '[data-role="drop-zone"]',
                dropZoneContent: '[data-role="drop-zone-content"]',
                uploadProgress: '[data-role="upload-progress"]',
                progressFill: '.panth-df-progress-fill',
                uploadedFile: '[data-role="uploaded-file"]',
                filePreview: '[data-role="file-preview"]',
                fileName: '[data-role="file-name"]',
                removeFile: '[data-role="remove-file"]',
                fileInput: '[data-role="file-input"]',
                uploadedValue: '[data-role="uploaded-value"]',
                wysiwygEditor: '.panth-df-wysiwyg-editor',
                wysiwygBtn: '.panth-df-wysiwyg-btn'
            }
        },

        _create: function () {
            this._initValidation();
            this._initFileUploads();
            this._initWysiwyg();
            this._bindSubmit();
        },

        _initValidation: function () {
            this.element.validation({
                errorPlacement: function (error, element) {
                    var field = element.closest('.panth-df-field');
                    var errorContainer = field.find('[data-role="field-error"]');
                    if (errorContainer.length) {
                        errorContainer.html(error.text()).show();
                    } else {
                        error.insertAfter(element);
                    }
                },
                highlight: function (element) {
                    $(element).closest('.panth-df-field').addClass('_error');
                },
                unhighlight: function (element) {
                    var field = $(element).closest('.panth-df-field');
                    field.removeClass('_error');
                    field.find('[data-role="field-error"]').hide();
                }
            });
        },

        _initFileUploads: function () {
            var self = this;
            var wrapper = this.element.closest('.panth-df-wrapper');

            wrapper.find(this.options.selectors.fileUpload).each(function () {
                var container = $(this);
                var fieldName = container.data('field-name');
                var dropZone = container.find(self.options.selectors.dropZone);
                var fileInput = container.find(self.options.selectors.fileInput);

                // Click to browse
                dropZone.on('click', function (e) {
                    if (!$(e.target).is('[data-role="remove-file"]')) {
                        fileInput.trigger('click');
                    }
                });

                // File selection
                fileInput.on('change', function () {
                    if (this.files && this.files[0]) {
                        self._uploadFile(container, fieldName, this.files[0]);
                    }
                });

                // Drag and drop
                dropZone.on('dragover', function (e) {
                    e.preventDefault();
                    dropZone.addClass('panth-df-dropzone--active');
                }).on('dragleave', function () {
                    dropZone.removeClass('panth-df-dropzone--active');
                }).on('drop', function (e) {
                    e.preventDefault();
                    dropZone.removeClass('panth-df-dropzone--active');
                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        self._uploadFile(container, fieldName, files[0]);
                    }
                });

                // Remove file
                container.find(self.options.selectors.removeFile).on('click', function (e) {
                    e.stopPropagation();
                    self._removeFile(container);
                });
            });
        },

        _uploadFile: function (container, fieldName, file) {
            var self = this;
            var config = this.options.config;

            // Validate size
            if (file.size > config.max_file_size) {
                this._showFieldError(container, $t('File size exceeds the maximum allowed size of %1 MB.').replace('%1', config.max_file_size_mb));
                return;
            }

            // Validate extension
            var ext = file.name.split('.').pop().toLowerCase();
            if (config.allowed_extensions && config.allowed_extensions.indexOf(ext) === -1) {
                this._showFieldError(container, $t('File type is not allowed. Allowed: %1').replace('%1', config.allowed_extensions.join(', ')));
                return;
            }

            // Show progress
            container.find(this.options.selectors.dropZoneContent).hide();
            container.find(this.options.selectors.uploadedFile).hide();
            container.find(this.options.selectors.uploadProgress).show();
            this._clearFieldError(container);

            var formData = new FormData();
            formData.append(fieldName, file);
            formData.append('field_name', fieldName);
            formData.append('form_key', $.mage.cookies.get('form_key'));

            $.ajax({
                url: config.upload_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    var xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            var pct = Math.round((e.loaded / e.total) * 100);
                            container.find(self.options.selectors.progressFill).css('width', pct + '%');
                        }
                    });
                    return xhr;
                },
                success: function (response) {
                    container.find(self.options.selectors.uploadProgress).hide();

                    if (response.success) {
                        container.find(self.options.selectors.uploadedValue).val(response.file);

                        // Show file preview
                        var preview = container.find(self.options.selectors.filePreview);
                        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                        if (imageExts.indexOf(ext) !== -1) {
                            preview.html('<img src="' + response.url + '" alt="Preview" style="max-width:60px;max-height:60px;"/>');
                        } else {
                            preview.html('<span class="panth-df-file-icon">&#128196;</span>');
                        }

                        container.find(self.options.selectors.fileName).text(response.name || file.name);
                        container.find(self.options.selectors.uploadedFile).show();
                    } else {
                        container.find(self.options.selectors.dropZoneContent).show();
                        self._showFieldError(container, response.message || $t('Upload failed.'));
                    }
                },
                error: function () {
                    container.find(self.options.selectors.uploadProgress).hide();
                    container.find(self.options.selectors.dropZoneContent).show();
                    self._showFieldError(container, $t('An error occurred during upload.'));
                }
            });
        },

        _removeFile: function (container) {
            container.find(this.options.selectors.uploadedValue).val('');
            container.find(this.options.selectors.uploadedFile).hide();
            container.find(this.options.selectors.dropZoneContent).show();
            container.find(this.options.selectors.fileInput).val('');
            container.find(this.options.selectors.progressFill).css('width', '0%');
        },

        _initWysiwyg: function () {
            var wrapper = this.element.closest('.panth-df-wrapper');

            wrapper.find(this.options.selectors.wysiwygBtn).on('click', function () {
                var command = $(this).data('command');
                document.execCommand(command, false, null);
            });

            wrapper.find(this.options.selectors.wysiwygEditor).on('input', function () {
                var fieldName = $(this).data('field-name');
                var field = wrapper.find('.panth-df-field[data-field-name="' + fieldName + '"]');
                field.find('input[type="hidden"][name="' + fieldName + '"]').val($(this).html());
            });
        },

        _bindSubmit: function () {
            var self = this;

            this.element.on('submit', function (e) {
                e.preventDefault();

                // Sync wysiwyg editors
                self.element.closest('.panth-df-wrapper').find(self.options.selectors.wysiwygEditor).each(function () {
                    var fieldName = $(this).data('field-name');
                    self.element.find('input[name="' + fieldName + '"]').val($(this).html());
                });

                if (!self.element.validation('isValid')) {
                    return;
                }

                self._submitForm();
            });
        },

        _submitForm: function () {
            var self = this;
            var wrapper = this.element.closest('.panth-df-wrapper');
            var config = this.options.config;

            // Hide messages
            wrapper.find(this.options.selectors.successMessage).hide();
            wrapper.find(this.options.selectors.globalError).hide();

            // Show loading
            this.element.find(this.options.selectors.btnText).hide();
            this.element.find(this.options.selectors.btnLoading).show();
            this.element.find(this.options.selectors.submitBtn).prop('disabled', true);

            var formData = new FormData(this.element[0]);

            $.ajax({
                url: config.submit_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self.element.hide();
                        wrapper.find(self.options.selectors.contentBelow).hide();
                        wrapper.find(self.options.selectors.successText).text(response.message);
                        wrapper.find(self.options.selectors.successMessage).show();

                        // Scroll to success message
                        $('html, body').animate({
                            scrollTop: wrapper.offset().top - 50
                        }, 400);

                        if (response.redirect_url) {
                            // Validate redirect is same-origin to prevent open redirect
                            try {
                                var redirectUrl = new URL(response.redirect_url, window.location.origin);
                                if (redirectUrl.origin === window.location.origin) {
                                    setTimeout(function () {
                                        window.location.href = redirectUrl.href;
                                    }, 2000);
                                }
                            } catch (e) {}
                        }
                    } else {
                        wrapper.find(self.options.selectors.globalErrorText).text(response.message);
                        wrapper.find(self.options.selectors.globalError).show();

                        // Set field errors
                        if (response.errors) {
                            $.each(response.errors, function (fieldName, errorMsg) {
                                var field = wrapper.find('.panth-df-field[data-field-name="' + fieldName + '"]');
                                if (field.length) {
                                    field.addClass('_error');
                                    field.find('[data-role="field-error"]').text(errorMsg).show();
                                }
                            });
                        }
                    }
                },
                error: function () {
                    wrapper.find(self.options.selectors.globalErrorText).text($t('A network error occurred. Please try again.'));
                    wrapper.find(self.options.selectors.globalError).show();
                },
                complete: function () {
                    self.element.find(self.options.selectors.btnText).show();
                    self.element.find(self.options.selectors.btnLoading).hide();
                    self.element.find(self.options.selectors.submitBtn).prop('disabled', false);
                }
            });
        },

        _showFieldError: function (container, message) {
            var field = container.closest('.panth-df-field');
            field.addClass('_error');
            field.find('[data-role="field-error"]').text(message).show();
        },

        _clearFieldError: function (container) {
            var field = container.closest('.panth-df-field');
            field.removeClass('_error');
            field.find('[data-role="field-error"]').hide();
        }
    });

    return $.panth.dynamicForm;
});
