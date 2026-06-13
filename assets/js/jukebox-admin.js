jQuery(document).ready(function($){
    
    // ==========================================
    // 1. IMPORT & SCAN TOOLS (from crjb_import_scan_page)
    // ==========================================

    $('#crjb_import_mp3s_btn').click(function(e) {
        e.preventDefault();
        if(!confirm('Scan the media library and import unlinked MP3s as new songs?')) return;
        
        let btn = $(this);
        let status = $('#crjb_import_status');
        btn.prop('disabled', true);
        status.css('color', '#000').text('Scanning media library...');

        $.post(crjbAdminData.ajaxurl, { action: 'crjb_import_media_library', security: crjbAdminData.geminiNonce }, function(res) {
            if(res.success) {
                status.css('color', '#28a745').text(res.data.msg);
            } else {
                status.css('color', '#d63638').text('Error: ' + res.data);
            }
            btn.prop('disabled', false);
        }).fail(function() {
            status.css('color', '#d63638').text('Server timeout or error. Check PHP error logs.');
            btn.prop('disabled', false);
        });
    });

    $('#crjb_bulk_scan_btn').click(function(e) {
        e.preventDefault();
        if(!confirm('This will scan a batch of up to 10 incomplete audio files via the Gemini API. This may take a minute. Proceed?')) return;
        
        let btn = $(this);
        let wipeBtn = $('#crjb_clear_ai_data_btn');
        let status = $('#crjb_bulk_status');
        btn.prop('disabled', true);
        wipeBtn.prop('disabled', true);
        status.css('color', '#000').text('Fetching incomplete songs and sending to Gemini...');

        $.post(crjbAdminData.ajaxurl, { action: 'crjb_gemini_bulk_scan', security: crjbAdminData.geminiNonce }, function(res) {
            if(res.success) {
                if(res.data.processed === 0) {
                    status.css('color', '#28a745').text(res.data.msg);
                } else {
                    status.css('color', '#28a745').text('Success! Scanned ' + res.data.processed + ' tracks. Click again to scan the next batch.');
                }
            } else {
                status.css('color', '#d63638').text('Error: ' + res.data);
            }
            btn.prop('disabled', false);
            wipeBtn.prop('disabled', false);
        }).fail(function() {
            status.css('color', '#d63638').text('Server timeout or error. Check PHP error logs.');
            btn.prop('disabled', false);
            wipeBtn.prop('disabled', false);
        });
    });

    $('#crjb_clear_ai_data_btn').click(function(e) {
        e.preventDefault();
        if(!confirm('WARNING: This will permanently delete ALL genres and lyrics from EVERY song in your library. You will need to rescan them afterwards. Are you sure?')) return;
        
        let btn = $(this);
        let scanBtn = $('#crjb_bulk_scan_btn');
        let status = $('#crjb_bulk_status');
        btn.prop('disabled', true);
        scanBtn.prop('disabled', true);
        status.css('color', '#d63638').text('Wiping all AI data...');

        $.post(crjbAdminData.ajaxurl, { action: 'crjb_gemini_clear_all', security: crjbAdminData.geminiNonce }, function(res) {
            if(res.success) {
                status.css('color', '#28a745').text(res.data.msg);
            } else {
                status.css('color', '#d63638').text('Error: ' + res.data);
            }
            btn.prop('disabled', false);
            scanBtn.prop('disabled', false);
        }).fail(function() {
            status.css('color', '#d63638').text('Server timeout or error.');
            btn.prop('disabled', false);
            scanBtn.prop('disabled', false);
        });
    });

    $('#crjb_cleanup_audio_btn').click(function(e) {
        e.preventDefault();
        if(!confirm('WARNING: This will permanently delete any MP3 from your WordPress Media Library that is not linked to the Jukebox. Proceed?')) return;
        
        let btn = $(this);
        let status = $('#crjb_cleanup_status');
        btn.prop('disabled', true);
        status.css('color', '#000').text('Scanning and cleaning up storage...');

        $.post(crjbAdminData.ajaxurl, { action: 'crjb_cleanup_orphaned_audio', security: crjbAdminData.geminiNonce }, function(res) {
            if(res.success) {
                status.css('color', '#28a745').text(res.data.msg);
            } else {
                status.css('color', '#d63638').text('Error: ' + res.data);
            }
            btn.prop('disabled', false);
        }).fail(function() {
            status.css('color', '#d63638').text('Server timeout or error. Check PHP error logs.');
            btn.prop('disabled', false);
        });
    });

    $('#crjb_folder_upload').on('change', function(e) {
        const files = e.target.files;
        if (files.length === 0) return;

        if (!confirm(`Ready to import ${files.length} audio files? Ensure your browser window stays open during the process.`)) {
            $(this).val('');
            return;
        }

        $('#crjb_upload_progress_container').show();
        let currentIndex = 0;
        const totalFiles = files.length;

        function uploadNextFile() {
            if (currentIndex >= totalFiles) {
                $('#crjb_upload_status').text('Import Complete!').css('color', '#28a745');
                $('#crjb_folder_upload').val('');
                return;
            }

            const file = files[currentIndex];
            
            // Parse webkitRelativePath (e.g., "The Beatles/Hey Jude.mp3")
            const pathParts = file.webkitRelativePath.split('/');
            let artistName = "Unknown Artist";
            let fileName = file.name;

            if (pathParts.length >= 2) {
                artistName = pathParts[pathParts.length - 2]; 
            }
            
            // Strip .mp3 extension for the title
            let songTitle = fileName.replace(/\.[^/.]+$/, "");

            $('#crjb_upload_status').text(`Uploading: ${songTitle} by ${artistName} (${currentIndex + 1}/${totalFiles})`).css('color', '#000');

            let formData = new FormData();
            formData.append('action', 'crjb_process_folder_upload');
            formData.append('security', crjbAdminData.folderNonce);
            formData.append('artist', artistName);
            formData.append('title', songTitle);
            formData.append('file', file);

            $.ajax({
                url: crjbAdminData.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    let percent = Math.round(((currentIndex + 1) / totalFiles) * 100);
                    $('#crjb_upload_progress_bar').css('width', percent + '%');
                    currentIndex++;
                    uploadNextFile(); 
                },
                error: function() {
                    $('#crjb_upload_status').text(`Error uploading ${songTitle}. Skipping...`).css('color', '#d63638');
                    currentIndex++;
                    uploadNextFile(); 
                }
            });
        }

        uploadNextFile(); 
    });


    // ==========================================
    // 2. SONG META BOX (from crjb_song_details_callback)
    // ==========================================

    var uploader;
    $('#crjb_upload_mp3_btn').click(function(e) {
        e.preventDefault();
        if (uploader) { uploader.open(); return; }
        uploader = wp.media({ title: 'Choose Network MP3', button: { text: 'Select Audio' }, multiple: false, library: { type: 'audio' } });
        uploader.on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            $('#crjb_audio_attachment_id').val(attachment.id);
            $('#full_audio_url').val(attachment.url);
            if($('#preview_url').val() === '') $('#preview_url').val(attachment.url);
        });
        uploader.open();
    });

    $('.crjb_upload_memo_btn').click(function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        var memoUploader = wp.media({ title: 'Choose Voice Memo', button: { text: 'Select Audio' }, multiple: false, library: { type: 'audio' } });
        memoUploader.on('select', function() {
            var attachment = memoUploader.state().get('selection').first().toJSON();
            $('#crjb_' + target + '_attachment_id').val(attachment.id);
            $('#' + target + '_audio_url').val(attachment.url);
        });
        memoUploader.open();
    });

    $('.crjb_clear_memo_btn').click(function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#crjb_' + target + '_attachment_id').val('');
        $('#' + target + '_audio_url').val('');
    });

    $('#crjb_gemini_scan_btn').click(function(e) {
        e.preventDefault();
        
        let current_url = $('#full_audio_url').val();
        let saved_url = $('#crjb_saved_audio_url').val();
        let isAutoDraft = $('#original_post_status').val() === 'auto-draft' || $('#post_status').val() === 'auto-draft';

        if (current_url === '') {
            alert('Please select a Track MP3 first.');
            return;
        }

        if (current_url !== saved_url || isAutoDraft) {
            alert('Please click "Publish" or "Update" first!\n\nThe MP3 needs to be saved to the database before the AI can securely analyze it.');
            return;
        }

        let btn = $(this);
        let id = $('#post_ID').val();
        btn.text('Scanning Audio...').prop('disabled', true);
        
        $.post(crjbAdminData.ajaxurl, { action: 'crjb_gemini_scan', song_id: id, security: crjbAdminData.geminiNonce }, function(res) {
            if(res.success) {
                let msg = "Success!";
                if(res.data.genres) msg += '\nGenres assigned: ' + res.data.genres.join(', ');
                if(res.data.explicit_status) msg += '\nRating status: ' + res.data.explicit_status;
                if(res.data.lyrics_status) msg += '\nLyrics status: ' + res.data.lyrics_status;
                alert(msg);
                location.reload(); 
            } else {
                alert('Error: ' + res.data);
                btn.text('✨ Analyze Audio').prop('disabled', false);
            }
        }).fail(function() {
            alert('Server timeout or error. The file may be too large.');
            btn.text('✨ Analyze Audio').prop('disabled', false);
        });
    });


    // ==========================================
    // 3. SCHEDULE META BOX (from crjb_schedule_details_callback)
    // ==========================================

    if ($.fn.select2) {
        $('.crjb-select2').select2({
            tags: true,
            tokenSeparators: [','],
            allowClear: true
        });
    }

});
