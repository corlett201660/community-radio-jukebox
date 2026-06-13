document.addEventListener("DOMContentLoaded", function() {
    const root = document.getElementById('crjb-app-root'), themeBtn = document.getElementById('crjb-theme-toggle');
    const infoToggleBtn = document.getElementById('crjb-info-toggle'), infoPanel = document.getElementById('crjb-info-panel');
    const scheduleToggleBtn = document.getElementById('crjb-schedule-toggle'), schedulePanel = document.getElementById('crjb-schedule-panel');
    const catalogToggleBtn = document.getElementById('crjb-catalog-toggle'), catalogContainer = document.getElementById('crjb-catalog-container');
    const alertContainer = document.getElementById('crjb-alert-container');
    
    // Pulling localized variables safely from WordPress
    const ajaxUrl = crjbJukeboxData.ajaxUrl;
    const securityNonce = crjbJukeboxData.securityNonce;
    const stationId = crjbJukeboxData.stationId;

    const live = document.getElementById('crjb-live-player'), prev = document.getElementById('crjb-preview-player');
    const syncBtn = document.getElementById('crjb-sync-btn'), discBtn = document.getElementById('crjb-disconnect-btn'), countDisp = document.getElementById('crjb-listener-count');
    const stopPreviewBtn = document.getElementById('crjb-stop-preview-btn');

    const CRJB_CACHE_NAME = 'crjb-offline-buffer-' + stationId;
    let cId = null, isSync = false, isOffline = false, catData = [], timer, offlineQueue = [];
    let lId = localStorage.getItem('crjb_l_id') || 'crjb_'+Math.random().toString(36).substr(2,9);
    let clientCatalogVersion = 0; let isPreviewing = false; let currentPreviewUrl = ''; 
    localStorage.setItem('crjb_l_id', lId);

    const availableOnlyCheckbox = document.getElementById('crjb-available-only');
    const savedAvailableOnly = localStorage.getItem('crjb_available_only') === 'true';
    availableOnlyCheckbox.checked = savedAvailableOnly;

    availableOnlyCheckbox.addEventListener('change', (e) => {
        localStorage.setItem('crjb_available_only', e.target.checked);
        renderCat();
    });

    const svgs = {
        moon: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>',
        sun: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>',
        users: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        broadcast: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M12 2v20"></path><path d="M8.5 6.5a5 5 0 0 0 0 7"></path><path d="M15.5 6.5a5 5 0 0 1 0 7"></path><path d="M5.5 3.5a10 10 0 0 0 0 13"></path><path d="M18.5 3.5a10 10 0 0 1 0 13"></path></svg>',
        play: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>',
        playLg: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>',
        checkCircle: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#28a745; margin-left:5px;" title="Buffered for Offline"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        file: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        arrowUp: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>',
        check: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        clock: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        lock: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
        stopwatch: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#28a745; margin-right:6px;"><circle cx="12" cy="13" r="8"></circle><polyline points="12 9 12 13 14 15"></polyline><line x1="12" y1="1" x2="12" y2="3"></line></svg>',
        alertTriangle: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:-0.125em;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        alertCircle: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:-0.125em;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
        successCheck: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:-0.125em;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        spinner: '<svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="crjb-spin" style="margin-right:8px;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>',
        plus: '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
    };
    
    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/[&<>'"]/g, function(tag) {
            const charsToReplace = { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' };
            return charsToReplace[tag] || tag;
        });
    }

    function trackJukeboxEvent(action, name, value = null) {
        if (typeof window._paq !== 'undefined') {
            if (value !== null) { window._paq.push(['trackEvent', 'Jukebox', action, name, value]); } 
            else { window._paq.push(['trackEvent', 'Jukebox', action, name]); }
        }
    }

    function recordSongPlay(title, isPreview = false) {
        if (isPreview) { trackJukeboxEvent('Preview Track', title); } 
        else {
            let currentCount = parseInt(sessionStorage.getItem('crjb_session_songs') || 0) + 1;
            sessionStorage.setItem('crjb_session_songs', currentCount);
            trackJukeboxEvent('Play Track', title);
            trackJukeboxEvent('Session Total Plays', currentCount.toString(), currentCount);
        }
    }

    function getVotedSongs() {
        let votes = JSON.parse(localStorage.getItem('crjb_user_votes_' + stationId) || '{}');
        let now = Date.now();
        let validIds = [];
        for (let id in votes) {
            if (now - votes[id] < 3600000) validIds.push(parseInt(id));
            else delete votes[id];
        }
        localStorage.setItem('crjb_user_votes_' + stationId, JSON.stringify(votes));
        return validIds;
    }

    function addVotedSong(id) {
        let votes = JSON.parse(localStorage.getItem('crjb_user_votes_' + stationId) || '{}');
        votes[id] = Date.now();
        localStorage.setItem('crjb_user_votes_' + stationId, JSON.stringify(votes));
    }

    let cachedUrls = new Set();
    async function refreshCacheSet() {
        if (!('caches' in window)) return;
        const cache = await caches.open(CRJB_CACHE_NAME);
        const keys = await cache.keys();
        cachedUrls.clear();
        keys.forEach(req => cachedUrls.add(req.url));
    }
    refreshCacheSet(); 

    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));

    if (infoToggleBtn && infoPanel) {
        infoToggleBtn.onclick = () => { 
            infoPanel.style.display = infoPanel.style.display === 'none' ? 'block' : 'none'; 
            if (schedulePanel) schedulePanel.style.display = 'none';
        };
    }

    if (scheduleToggleBtn && schedulePanel) {
        scheduleToggleBtn.onclick = () => { 
            schedulePanel.style.display = schedulePanel.style.display === 'none' ? 'block' : 'none'; 
            if (infoPanel) infoPanel.style.display = 'none';
        };
    }

    const catalogVisible = localStorage.getItem('crjb_catalog_visible') !== 'false';
    catalogContainer.style.display = catalogVisible ? 'block' : 'none';
    catalogToggleBtn.style.opacity = catalogVisible ? '1' : '0.5';
    catalogToggleBtn.onclick = () => {
        const isHidden = catalogContainer.style.display === 'none';
        catalogContainer.style.display = isHidden ? 'block' : 'none';
        localStorage.setItem('crjb_catalog_visible', isHidden);
        catalogToggleBtn.style.opacity = isHidden ? '1' : '0.5';
    };

    if (stopPreviewBtn) { stopPreviewBtn.onclick = () => { stopPreview(); }; }

    function showNotification(message, type) {
        var alertType = type ? type : 'danger';
        var icon = alertType === 'danger' ? svgs.alertTriangle : (alertType === 'warning' ? svgs.alertCircle : svgs.successCheck);
        var alertHtml = '<div class="alert alert-' + alertType + ' alert-dismissible fade show" role="alert">' + icon + escapeHTML(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        alertContainer.insertAdjacentHTML('beforeend', alertHtml);
        var newAlert = alertContainer.lastElementChild;
        setTimeout(function() { 
            if (newAlert) {
                newAlert.style.opacity = '0';
                newAlert.style.transition = 'opacity 0.3s ease';
                setTimeout(function() { newAlert.remove(); }, 300);
            }
        }, 4000);
    }

    function renderQueueUI(queueArray) {
        let votedIds = getVotedSongs();
        const ql = document.getElementById('crjb-queue-list'); 
        ql.innerHTML = '';
        queueArray.forEach(s => {
            let sTitle = escapeHTML(s.title);
            let sArtist = escapeHTML(s.artist);
            let sLink = escapeHTML(s.permalink);
            
            let eBadge = s.is_explicit ? '<span class="crjb-explicit-badge" title="Explicit Content">E</span>' : '';
            let cIcon = (s.url && cachedUrls.has(s.url)) ? svgs.checkCircle : '';
            let lyricsBtn = '<a href="' + sLink + '" target="_blank" class="crjb-btn" title="View Track Details" style="background:var(--crjb-sec); padding:10px 14px;">' + svgs.file + '</a>';
            
            let safeVoteTitle = sTitle.replace(/'/g, "\\'");
            let safeArtistQuote = sArtist.replace(/'/g, "\\'");
            let safePreviewUrl = escapeHTML(s.preview_url);
            
            let genresArray = s.genre ? s.genre.split(', ') : [];
            let gBadge = genresArray.length > 0 
                ? '<div style="margin-top: 6px;">' + genresArray.map(g => '<span class="crjb-genre-badge" style="margin-left: 0; margin-right: 6px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="viewGenre(\'' + escapeHTML(g).replace(/'/g, "\\'") + '\')">' + escapeHTML(g) + '</span>').join('') + '</div>'
                : '';
            
            let voteBtnHtml = votedIds.includes(s.id) 
                ? '<button class="crjb-btn crjb-btn-vote crjb-voted" disabled>' + svgs.check + ' ' + (s.votes || 0) + '</button>'
                : '<button class="crjb-btn crjb-btn-vote" onclick="voteSong(' + s.id + ', \'' + safeVoteTitle + '\')">' + svgs.arrowUp + ' ' + (s.votes || 0) + '</button>';

            ql.innerHTML += '<li class="crjb-track-item"><div class="crjb-track-info"><h4 style="margin:0 0 5px 0; display:flex; align-items:center;"><a href="' + sLink + '" style="color:inherit; text-decoration:none;" target="_blank">' + sTitle + '</a> ' + eBadge + ' ' + cIcon + '</h4><div style="margin-bottom: 2px;"><span class="crjb-clickable-artist" onclick="viewArtist(this.innerText)">' + sArtist + '</span></div>' + gBadge + '</div><div style="display:flex; gap:8px; align-items: center;">' + lyricsBtn + '<button class="crjb-btn" onclick="previewSong(\'' + safePreviewUrl + '\', \'' + safeVoteTitle + '\', \'' + safeArtistQuote + '\')">' + svgs.play + '</button>' + voteBtnHtml + '</div></li>';
        });
    }

    async function bufferNextTracks(tracks) {
        if (!('caches' in window)) return;
        const cache = await caches.open(CRJB_CACHE_NAME);
        let updated = false;
        
        for (const song of tracks.slice(0, 5)) {
            if(song && song.url) {
                const response = await cache.match(song.url);
                if (!response) { try { await cache.add(song.url); updated = true; } catch(e) { } }
            }
            if(song && song.preview_url) {
                const responsePrev = await cache.match(song.preview_url);
                if (!responsePrev) { try { await cache.add(song.preview_url); updated = true; } catch(e) { } }
            }
        }

        if (catData && catData.length > 0) {
            let unCached = catData.filter(s => s.url && !cachedUrls.has(s.url));
            if (unCached.length > 0) {
                unCached = unCached.sort(() => 0.5 - Math.random()).slice(0, 3);
                for (const song of unCached) {
                    try { await cache.add(song.url); updated = true; } catch(e) { }
                }
            }
        }

        if (updated) {
            await refreshCacheSet();
            renderCat(); 
            if (typeof offlineQueue !== 'undefined') renderQueueUI(offlineQueue); 
        }
    }

    let liveBlobUrl = null;
    let prevBlobUrl = null;

    async function getAndSetCachedAudio(url, audioElement, isPreview = false) {
        if (!('caches' in window)) return false;
        const cache = await caches.open(CRJB_CACHE_NAME);
        const response = await cache.match(url);
        if (response) { 
            const blob = await response.blob(); 
            if (isPreview) {
                if (prevBlobUrl) URL.revokeObjectURL(prevBlobUrl);
                prevBlobUrl = URL.createObjectURL(blob);
                audioElement.src = prevBlobUrl;
            } else {
                if (liveBlobUrl) URL.revokeObjectURL(liveBlobUrl);
                liveBlobUrl = URL.createObjectURL(blob);
                audioElement.src = liveBlobUrl;
            }
            return true;
        }
        return false;
    }

    const savedTheme = localStorage.getItem('crjb_theme') || 'light';
    root.dataset.theme = savedTheme; themeBtn.innerHTML = savedTheme === 'light' ? svgs.moon : svgs.sun;
    
    themeBtn.onclick = () => { 
        let t = root.dataset.theme === 'light' ? 'dark' : 'light'; root.dataset.theme = t; localStorage.setItem('crjb_theme', t);
        themeBtn.innerHTML = t === 'light' ? svgs.moon : svgs.sun;
    };

    syncBtn.onclick = () => { 
        isSync = true; syncBtn.innerHTML = svgs.spinner + ' Connecting...'; poll(); 
    };

    discBtn.onclick = () => { 
        isSync = false; isOffline = false; live.pause(); live.removeAttribute('src'); live.load(); 
        if(!isPreviewing) { prev.pause(); prev.removeAttribute('src'); prev.load(); }
        cId = null; window.currentPhaseId = null; discBtn.style.display = 'none'; syncBtn.style.display = 'block'; 
        syncBtn.innerHTML = svgs.broadcast + ' Connect'; poll(); 
    };

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible" && isSync && live.paused) { poll(); }
    });

    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', () => {
            if (isSync) { poll(); live.play().catch(e=>{}); }
        });
        navigator.mediaSession.setActionHandler('pause', () => {
            if (isSync) {
                live.pause();
                syncBtn.style.display = 'block'; discBtn.style.display = 'none';
                syncBtn.innerHTML = svgs.playLg + ' Resume Sync';
            }
        });
    }
    
    live.addEventListener('play', () => {
        if (isSync && !isPreviewing) { syncBtn.style.display = 'none'; discBtn.style.display = 'block'; }
    });

    live.onended = async () => {
        if (isSync && !isOffline) poll(); 
        
        if (isOffline && isSync) {
            let nextSong = null;
            if (offlineQueue.length > 0) {
                nextSong = offlineQueue.shift();
                renderQueueUI(offlineQueue);
            } else {
                const playableSongs = catData.filter(s => s.url && cachedUrls.has(s.url) && !s.is_locked_by_schedule && s.id !== cId);
                if (playableSongs.length > 0) { nextSong = playableSongs[Math.floor(Math.random() * playableSongs.length)]; }
            }

            if (nextSong) {
                const success = await getAndSetCachedAudio(nextSong.url, live, false);
                if (success) {
                    cId = nextSong.id; root.dataset.currentSongId = nextSong.id;
                    
                    let eBadge = nextSong.is_explicit ? '<span class="crjb-explicit-badge">E</span>' : '';
                    let sLink = escapeHTML(nextSong.permalink);
                    let lyricsLink = '<a href="' + sLink + '" target="_blank" style="margin-left:8px; font-size:14px; color:var(--crjb-accent);" title="View Track Details">' + svgs.file + '</a>';
                    
                    document.getElementById('crjb-np-title').innerHTML = escapeHTML(nextSong.title) + ' ' + eBadge + lyricsLink; 
                    document.getElementById('crjb-np-artist').innerHTML = '<span class="crjb-clickable-artist" onclick="viewArtist(this.innerText)">' + escapeHTML(nextSong.artist) + '</span>';
                    
                    let tipContainer = document.getElementById('crjb-np-tip-container');
                    let tipBtn = document.getElementById('crjb-np-tip-btn');
                    if (nextSong.tip_url && !isPreviewing) {
                        tipBtn.href = escapeHTML(nextSong.tip_url); tipContainer.style.display = 'block';
                        tipBtn.style.transform = 'scale(1.03)'; setTimeout(() => { tipBtn.style.transform = 'scale(1)'; }, 300);
                    } else { tipContainer.style.display = 'none'; }

                    let bannerEl = document.getElementById('crjb-np-banner'), bannerTextEl = document.getElementById('crjb-np-banner-text');
                    if (nextSong.banner && !isPreviewing) {
                        bannerEl.style.display = 'block'; bannerTextEl.innerHTML = nextSong.banner; 
                    } else { bannerEl.style.display = 'none'; bannerTextEl.innerHTML = ''; }
                    
                    if ('mediaSession' in navigator) navigator.mediaSession.metadata = new MediaMetadata({ title: nextSong.title, artist: nextSong.artist, album: 'Community Radio Jukebox' });
                    recordSongPlay(nextSong.title, false);

                    live.onloadedmetadata = () => {
                        let dur = Math.floor(live.duration); if (isNaN(dur)) dur = 180;
                        live.currentTime = 0; clearInterval(timer); 
                        let localStart = Math.floor(Date.now()/1000);
                        timer = setInterval(() => {
                            let rem = dur - (Math.floor(Date.now()/1000) - localStart); if(rem < 0) rem = 0;
                            let m = Math.floor(rem/60).toString().padStart(2,'0'), s = (rem%60).toString().padStart(2,'0');
                            if (!isPreviewing) document.getElementById('crjb-np-time').innerHTML = svgs.clock + ' ' + m + ':' + s;
                            if(rem === 0) clearInterval(timer);
                        }, 1000);
                    };
                    if (!isPreviewing) {
                        live.play().catch(e => {
                            syncBtn.style.display = 'block'; discBtn.style.display = 'none';
                            syncBtn.innerHTML = svgs.playLg + ' Resume Sync';
                        });
                    }
                } else { showNotification('Next track unavailable offline.', 'warning'); live.onended(); }
            } else {
                if(catData.length > 0) {
                    let emergencySongs = catData.filter(s => s.url && cachedUrls.has(s.url) && !s.is_locked_by_schedule);
                    if(emergencySongs.length > 0) {
                         nextSong = emergencySongs[Math.floor(Math.random() * emergencySongs.length)];
                         const s = await getAndSetCachedAudio(nextSong.url, live, false);
                         if (s) {
                             cId = nextSong.id; root.dataset.currentSongId = nextSong.id;
                             let eBadge = nextSong.is_explicit ? '<span class="crjb-explicit-badge">E</span>' : '';
                             let sLink = escapeHTML(nextSong.permalink);
                             let lyricsLink = '<a href="' + sLink + '" target="_blank" style="margin-left:8px; font-size:14px; color:var(--crjb-accent);" title="View Track Details">' + svgs.file + '</a>';
                             
                             document.getElementById('crjb-np-title').innerHTML = escapeHTML(nextSong.title) + ' ' + eBadge + lyricsLink; 
                             document.getElementById('crjb-np-artist').innerHTML = '<span class="crjb-clickable-artist" onclick="viewArtist(this.innerText)">' + escapeHTML(nextSong.artist) + '</span>';
                             
                             live.onloadedmetadata = () => { live.currentTime = 0; if(!isPreviewing) live.play().catch(e=>{}); };
                         }
                    }
                } else { showNotification('No cached songs available.', 'warning'); }
            }
        }
    };

    function startClock(dur, start, serv) {
        clearInterval(timer); let localStart = Math.floor(Date.now()/1000) - (serv - start);
        timer = setInterval(() => {
            let rem = dur - (Math.floor(Date.now()/1000) - localStart); if(rem < 0) rem = 0;
            let m = Math.floor(rem/60).toString().padStart(2,'0'), s = (rem%60).toString().padStart(2,'0');
            if (!isPreviewing) document.getElementById('crjb-np-time').innerHTML = svgs.clock + ' ' + m + ':' + s;
            if(rem === 0 && !isOffline) { clearInterval(timer); poll(); }
        }, 1000);
    }

    function poll() {
        fetch(ajaxUrl + '?action=crjb_get_state&listener_id=' + lId + '&is_listening=' + isSync + '&security=' + securityNonce + '&station=' + stationId)
        .then(r => r.json()).then(d => {
            if(!d.success) return;
            
            if (d.data.upcoming_events) {
                const sl = document.getElementById('crjb-schedule-list');
                if (sl) {
                    if (d.data.upcoming_events.length === 0) {
                        sl.innerHTML = '<li style="color:var(--crjb-sec); font-style:italic;">No upcoming events scheduled. Enjoy Open Play!</li>';
                    } else {
                        sl.innerHTML = '';
                        d.data.upcoming_events.forEach(ev => {
                            let startTs = parseInt(ev.timestamp), sParts = ev.start_time.split(':'), eParts = ev.end_time.split(':');
                            let sMins = (parseInt(sParts[0]) * 60) + parseInt(sParts[1]), eMins = (parseInt(eParts[0]) * 60) + parseInt(eParts[1]);
                            if (eMins < sMins) { eMins += 1440; } 
                            let endTs = startTs + ((eMins - sMins) * 60);

                            let startD = new Date(startTs * 1000), endD = new Date(endTs * 1000);
                            let formatTime = (d) => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                            let timeFmt = formatTime(startD) + ' - ' + formatTime(endD);
                            
                            let today = new Date();
                            let isToday = startD.getDate() === today.getDate() && startD.getMonth() === today.getMonth() && startD.getFullYear() === today.getFullYear();
                            let dayLabel = isToday ? 'Today' : startD.toLocaleDateString([], { weekday: 'long' });

                            sl.innerHTML += '<li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05);">' +
                                '<div>' +
                                    '<strong style="display:block; color:var(--crjb-accent); font-size:14px;">' + escapeHTML(ev.title) + '</strong>' +
                                    '<span style="color:var(--crjb-sec); font-size:12px;">' + timeFmt + '</span>' +
                                '</div>' +
                                '<div style="background:var(--crjb-panel); padding:4px 10px; border-radius:8px; font-weight:700; font-size:11px; border:1px solid var(--crjb-border);">' + dayLabel + '</div>' +
                            '</li>';
                        });
                    }
                }
            }
            
            if (d.data.station_label) {
                let badge = document.getElementById('crjb-station-badge-text');
                if(badge) badge.innerText = escapeHTML(d.data.station_label);
            }

            if (d.data.now_playing === null && d.data.queue.length === 0) {
                document.getElementById('crjb-np-title').innerText = "Station Empty";
                document.getElementById('crjb-np-artist').innerHTML = "No tracks found for this criteria.";
                document.getElementById('crjb-np-tip-container').style.display = 'none';
                document.getElementById('crjb-np-banner').style.display = 'none';
                return;
            }

            if (isOffline && isSync) {
                showNotification("Connection restored. Re-syncing.", "success");
                isOffline = false; cId = null; window.currentPhaseId = null;
                document.getElementById('crjb-np-status-label').innerText = 'On Air';
                document.getElementById('crjb-np-status-label').style.color = '';
            }
            countDisp.innerHTML = svgs.users + ' ' + d.data.listener_count;
            
            if (d.data.catalog_version && d.data.catalog_version > clientCatalogVersion) {
                if (clientCatalogVersion !== 0) { loadCat(); if ('caches' in window) caches.delete(CRJB_CACHE_NAME); }
                clientCatalogVersion = d.data.catalog_version;
            }

            if (d.data.queue && d.data.now_playing) {
                offlineQueue = d.data.queue.map(s => ({...s})); 
                bufferNextTracks([d.data.now_playing, ...d.data.queue]);
            }

            const np = d.data.now_playing; 
            window.currentNpData = np ? np : null;
            if(!np) return;
            
            let uiId = root.dataset.currentSongId;
            if(uiId !== String(np.id) && !isPreviewing) {
                root.dataset.currentSongId = np.id;
                let eBadge = np.is_explicit ? '<span class="crjb-explicit-badge">E</span>' : '';
                let sLink = escapeHTML(np.permalink);
                let lyricsLink = '<a href="' + sLink + '" target="_blank" style="margin-left:8px; font-size:14px; color:var(--crjb-accent);" title="View Track Details">' + svgs.file + '</a>';
                document.getElementById('crjb-np-title').innerHTML = escapeHTML(np.title) + ' ' + eBadge + lyricsLink; 
                document.getElementById('crjb-np-artist').innerHTML = '<span class="crjb-clickable-artist" onclick="viewArtist(this.innerText)">' + escapeHTML(np.artist) + '</span>';
                
                let tipContainer = document.getElementById('crjb-np-tip-container');
                let tipBtn = document.getElementById('crjb-np-tip-btn');
                if (np.tip_url && !isPreviewing) {
                    tipBtn.href = escapeHTML(np.tip_url);
                    tipContainer.style.display = 'block';
                    tipBtn.style.transform = 'scale(1.03)';
                    setTimeout(() => { tipBtn.style.transform = 'scale(1)'; }, 300);
                } else { tipContainer.style.display = 'none'; }

                let bannerEl = document.getElementById('crjb-np-banner');
                let bannerTextEl = document.getElementById('crjb-np-banner-text');
                if (np.banner) {
                    bannerEl.style.display = 'block'; bannerTextEl.innerHTML = np.banner; 
                } else {
                    bannerEl.style.display = 'none';
                    bannerTextEl.innerHTML = '';
                }

                if ('mediaSession' in navigator) navigator.mediaSession.metadata = new MediaMetadata({ title: np.title, artist: np.artist, album: 'Community Radio Jukebox' });
                recordSongPlay(np.title, false); 
                startClock(np.duration, np.start_timestamp, np.server_now);
                loadCat(); 
            }

            if(isSync && prev.paused) {
                let offset = np.server_now - np.start_timestamp;
                let iDur = parseFloat(np.intro_duration) || 0, sDur = parseFloat(np.song_duration) || 0, oDur = parseFloat(np.outro_duration) || 0;
                let activeUrl = np.url, activeOffset = offset, targetPhase = 'song';

                if (iDur > 0 && offset < iDur) {
                    activeUrl = np.intro_url; activeOffset = offset; targetPhase = 'intro';
                } else if (offset < iDur + sDur) {
                    activeUrl = np.url; activeOffset = offset - iDur; targetPhase = 'song';
                } else if (oDur > 0 && offset < iDur + sDur + oDur) {
                    activeUrl = np.outro_url; activeOffset = offset - iDur - sDur; targetPhase = 'outro';
                }

                let phaseId = np.id + '_' + targetPhase;

                if(window.currentPhaseId !== phaseId) {
                    window.currentPhaseId = phaseId; cId = np.id; 
                    live.src = activeUrl;
                    live.onloadedmetadata = () => { 
                        if (!isSync) return; 
                        live.currentTime = activeOffset > 0 ? activeOffset : 0; 
                        if (!isPreviewing) {
                            live.play().then(() => { 
                                if(syncBtn) syncBtn.style.display = 'none'; 
                                if(discBtn) discBtn.style.display = 'block'; 
                            }).catch(e => {
                                if(syncBtn) { syncBtn.style.display = 'block'; syncBtn.innerHTML = svgs.playLg + ' Resume Sync'; }
                                if(discBtn) discBtn.style.display = 'none';
                            });
                        }
                    };
                    live.load();
                } else if(live.paused && !isPreviewing) { 
                    live.currentTime = activeOffset; 
                    live.play().then(() => { 
                        if(syncBtn) syncBtn.style.display = 'none'; 
                        if(discBtn) discBtn.style.display = 'block'; 
                    }).catch(e => { }); 
                }
                else if(Math.abs(live.currentTime - activeOffset) > 3) {
                    live.currentTime = activeOffset;
                }
            }

            if (!isPreviewing && isSync) {
                if (syncBtn) syncBtn.style.display = 'none';
                if (discBtn) discBtn.style.display = 'block';
            }
            
            renderQueueUI(d.data.queue);
        })
        .catch(async (err) => {
            if (isSync && !isOffline) {
                isOffline = true; 
                
                if (!cId) { showNotification("Offline Mode active. Starting Local Radio.", "warning"); } 
                else { showNotification("Connection lost. Switching to Local Buffer.", "warning"); }

                document.getElementById('crjb-np-status-label').innerText = 'Offline Mode';
                document.getElementById('crjb-np-status-label').style.color = '#dc3545';
                
                const currentPlayTime = live.currentTime;
                const currentSong = cId ? catData.find(s => s.id === cId) : null;
                
                if (currentSong && currentSong.url) {
                    const wasPaused = live.paused;
                    const success = await getAndSetCachedAudio(currentSong.url, live, false);
                    if (success) {
                        live.onloadedmetadata = () => { 
                            live.currentTime = currentPlayTime || 0; 
                            if (!wasPaused && !isPreviewing) {
                                live.play().catch(e => {
                                    if(syncBtn) { syncBtn.style.display = 'block'; syncBtn.innerHTML = svgs.playLg + ' Resume Sync'; }
                                    if(discBtn) discBtn.style.display = 'none';
                                });
                            } 
                        };
                        live.load();
                    } else { live.onended(); }
                } else { live.onended(); }
            }
        });
    }

    let currentArtistFilter = null;
    let currentGenreFilter = null;

    window.viewArtist = (artistName) => {
        currentArtistFilter = artistName; currentGenreFilter = null; 
        document.getElementById('crjb-filter-text').innerText = 'Showing tracks by: ' + artistName;
        document.getElementById('crjb-artist-filter-header').style.display = 'flex'; renderCat();
        document.getElementById('crjb-artist-filter-header').scrollIntoView({behavior: 'smooth', block: 'start'});
    };
    
    window.viewGenre = (genreName) => {
        currentGenreFilter = genreName; currentArtistFilter = null; 
        document.getElementById('crjb-filter-text').innerText = 'Showing genre: ' + genreName;
        document.getElementById('crjb-artist-filter-header').style.display = 'flex'; renderCat();
        document.getElementById('crjb-artist-filter-header').scrollIntoView({behavior: 'smooth', block: 'start'});
    };

    window.clearArtistFilter = () => { 
        currentArtistFilter = null; currentGenreFilter = null; 
        document.getElementById('crjb-artist-filter-header').style.display = 'none'; 
        renderCat(); 
    };

    function loadCat() { 
        fetch(ajaxUrl + '?action=crjb_get_catalog&security=' + securityNonce + '&station=' + stationId).then(r => r.json()).then(async d => { 
            if(d.success) { 
                catData = d.data.catalog; 
                localStorage.setItem('crjb_offline_catalog_' + stationId, JSON.stringify(catData));
                await refreshCacheSet();
                renderCat(); 
            } 
        }).catch(async e => {
            const savedCat = localStorage.getItem('crjb_offline_catalog_' + stationId);
            if (savedCat) {
                catData = JSON.parse(savedCat);
                await refreshCacheSet();
                renderCat();
            }
        }); 
    }

    function renderCat() {
        const l = document.getElementById('crjb-catalog-list'), s = document.getElementById('crjb-catalog-sort').value;
        const showAvailable = availableOnlyCheckbox.checked;

        let sorted = [...catData];
        if (currentArtistFilter) sorted = sorted.filter(song => song.artist === currentArtistFilter);
        if (currentGenreFilter) sorted = sorted.filter(song => song.genre && song.genre.split(', ').includes(currentGenreFilter));
        
        if (showAvailable) {
            sorted = sorted.filter(song => song.cooldown <= 0 && !song.is_playing && !song.is_locked_by_schedule);
        }
        
        if(s === 'title') sorted.sort((a,b) => a.title.localeCompare(b.title)); else if(s === 'artist') sorted.sort((a,b) => a.artist.localeCompare(b.artist)); else if(s === 'newest') sorted.sort((a,b) => b.id - a.id);
        
        if(sorted.length === 0) { 
            let emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found.</li>';
            
            if (catData.length > 0) {
                let targetData = [...catData];
                if (currentArtistFilter) targetData = targetData.filter(song => song.artist === currentArtistFilter);
                if (currentGenreFilter) targetData = targetData.filter(song => song.genre && song.genre.split(', ').includes(currentGenreFilter));
                
                if (targetData.length > 0) {
                    let nextUnlockTs = Infinity;
                    let nextUnlockMsg = "";
                    
                    targetData.forEach(s => {
                        if (s.cooldown > 0 && !s.is_locked_by_schedule) {
                            let cdTs = Date.now() + (s.cooldown * 1000);
                            if (cdTs < nextUnlockTs) {
                                nextUnlockTs = cdTs;
                                nextUnlockMsg = "Next track available at " + new Date(cdTs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                            }
                        } else if (s.is_locked_by_schedule && s.unlock_timestamp) {
                            let evTs = s.unlock_timestamp * 1000;
                            if (evTs < nextUnlockTs) {
                                nextUnlockTs = evTs;
                                nextUnlockMsg = escapeHTML(s.unlock_msg); 
                            }
                        }
                    });

                    if (window.currentNpData && typeof offlineQueue !== 'undefined' && offlineQueue.length === 0) {
                        let autoDjCanPlay = catData.some(s => s.cooldown <= 0 && !s.is_locked_by_schedule && s.id != window.currentNpData.id);
                        if (!autoDjCanPlay) {
                            let serverEndTime = window.currentNpData.start_timestamp + window.currentNpData.duration;
                            let localOffset = Date.now() - (window.currentNpData.server_now * 1000);
                            let localEndsAt = (serverEndTime * 1000) + localOffset;
                            
                            if (localEndsAt < nextUnlockTs && localEndsAt > Date.now()) {
                                nextUnlockTs = localEndsAt;
                                nextUnlockMsg = "Available when current song ends";
                            }
                        }
                    }
                    
                    if (nextUnlockTs !== Infinity) {
                        emptyMsg = '<li style="padding:30px 15px; text-align:center; color:var(--crjb-sec); background:var(--crjb-panel); border:1px dashed var(--crjb-border); border-radius:12px; grid-column: 1 / -1;">' +
                            '<svg width="2em" height="2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px; color:var(--crjb-accent);"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg><br>' +
                            '<strong style="font-size:15px; color:var(--crjb-text);">No tracks currently available</strong><br>' +
                            '<span style="font-size:13px; font-weight:600; display:inline-block; margin-top:8px; background:rgba(0,115,170,0.1); color:var(--crjb-accent); padding:4px 12px; border-radius:12px;">' + nextUnlockMsg + '</span>' +
                        '</li>';
                    } else {
                        emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks currently available to request.</li>';
                    }
                } else if (currentArtistFilter) { emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found for this artist.</li>';
                } else if (currentGenreFilter) { emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found for this genre.</li>'; }
            }
            
            l.innerHTML = emptyMsg; 
            return; 
        }
        
        l.innerHTML = '';
        let votedIds = getVotedSongs();

        sorted.forEach(s => {
            let sTitle = escapeHTML(s.title); let sArtist = escapeHTML(s.artist); let sLink = escapeHTML(s.permalink);
            let badge = ''; let isLocked = s.cooldown > 0 || s.is_playing || s.is_locked_by_schedule;
            let eBadge = s.is_explicit ? '<span class="crjb-explicit-badge" title="Explicit Content">E</span>' : '';
            
            if (s.is_locked_by_schedule) { badge = '<div class="crjb-cooldown-badge" style="background:#8e44ad; color:#fff; border:1px solid #732d91;">' + svgs.lock + ' ' + escapeHTML(s.unlock_msg) + '</div>'; } 
            else if (s.is_playing) { badge = '<div class="crjb-cooldown-badge" style="background:var(--crjb-accent); color:#fff;">ON AIR</div>'; } 
            else if (s.cooldown > 0) { badge = '<div class="crjb-cooldown-badge">' + svgs.clock + ' Avail ' + new Date(Date.now() + s.cooldown * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + '</div>'; }
            
            let cIcon = (s.url && cachedUrls.has(s.url)) ? svgs.checkCircle : '';
            let lyricsBtn = '<a href="' + sLink + '" target="_blank" class="crjb-btn" title="View Track Details" style="background:var(--crjb-sec); padding:10px 14px;">' + svgs.file + '</a>';
            
            let safeVoteTitle = sTitle.replace(/'/g, "\\'"); let safeArtistQuote = sArtist.replace(/'/g, "\\'"); let safePreviewUrl = escapeHTML(s.preview_url);

            let genresArray = s.genre ? s.genre.split(', ') : [];
            let gBadge = genresArray.length > 0 ? '<div style="margin-top: 6px;">' + genresArray.map(g => '<span class="crjb-genre-badge" style="margin-left: 0; margin-right: 6px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="viewGenre(\'' + escapeHTML(g).replace(/'/g, "\\'") + '\')">' + escapeHTML(g) + '</span>').join('') + '</div>' : '';

            let voteBtnHtml = votedIds.includes(s.id)
                ? '<button class="crjb-btn crjb-btn-vote crjb-voted" disabled>' + svgs.check + '</button>'
                : '<button class="crjb-btn crjb-btn-vote" onclick="voteSong(' + s.id + ', \'' + safeVoteTitle + '\')">' + svgs.plus + '</button>';

            l.innerHTML += '<li class="crjb-track-item ' + (isLocked ? 'crjb-locked' : '') + '"><div class="crjb-track-info"><h4 style="margin:0 0 5px 0; display:flex; align-items:center;"><a href="' + sLink + '" style="color:inherit; text-decoration:none;" target="_blank">' + sTitle + '</a> ' + eBadge + ' ' + cIcon + '</h4><div style="margin-bottom: 2px;"><span class="crjb-clickable-artist" onclick="viewArtist(this.innerText)">' + sArtist + '</span></div>' + badge + gBadge + '</div><div style="display:flex; gap:8px; align-items: center;">' + lyricsBtn + '<button class="crjb-btn" onclick="previewSong(\'' + safePreviewUrl + '\', \'' + safeVoteTitle + '\', \'' + safeArtistQuote + '\')">' + svgs.play + '</button>' + voteBtnHtml + '</div></li>';
        });
    }
    
    function stopPreview() {
        isPreviewing = false; currentPreviewUrl = ''; prev.pause(); prev.removeAttribute('src');
        document.getElementById('crjb-np-status-label').innerText = isOffline ? 'Offline Mode' : 'On Air';
        document.getElementById('crjb-np-status-label').style.color = isOffline ? '#dc3545' : '';
        if (stopPreviewBtn) stopPreviewBtn.style.display = 'none';
        root.dataset.currentSongId = null; 
        
        if (isSync) {
            if (syncBtn) syncBtn.style.display = 'none';
            if (discBtn) discBtn.style.display = 'block';
            live.play().catch(e => {
                if (syncBtn) { syncBtn.style.display = 'block'; syncBtn.innerHTML = svgs.playLg + ' Resume Sync'; }
                if (discBtn) discBtn.style.display = 'none';
            });
        } else {
            if (syncBtn) { syncBtn.style.display = 'block'; syncBtn.innerHTML = svgs.broadcast + ' Connect'; }
            if (discBtn) discBtn.style.display = 'none';
        }
        poll(); 
    }

    window.previewSong = async (u, title, artist) => { 
        if(!u) return; 
        if(isPreviewing && currentPreviewUrl === u) { stopPreview(); return; }
        if(isSync) live.pause(); 
        
        isPreviewing = true; currentPreviewUrl = u;
        document.getElementById('crjb-np-status-label').innerText = 'Local Broadcast'; document.getElementById('crjb-np-status-label').style.color = '#28a745';
        document.getElementById('crjb-np-title').innerText = title; document.getElementById('crjb-np-artist').innerHTML = artist;
        document.getElementById('crjb-np-tip-container').style.display = 'none'; 
        
        var existingBanner = document.getElementById('crjb-np-banner');
        if(existingBanner) existingBanner.style.display = 'none';
        
        if (stopPreviewBtn) stopPreviewBtn.style.display = 'block';
        if (syncBtn) syncBtn.style.display = 'none';
        if (discBtn) discBtn.style.display = 'none';

        let playUrl = u;
        if (isOffline) {
            const success = await getAndSetCachedAudio(u, prev, true);
            if (!success) {
                showNotification('Preview audio not buffered for offline use.', 'warning');
                stopPreview(); return;
            }
        } else { prev.src = u; }

        recordSongPlay(title, true); 

        prev.play().catch(e=>{}); 
        prev.ontimeupdate = () => { 
            if(isPreviewing) { let rem = 30 - Math.floor(prev.currentTime); document.getElementById('crjb-np-time').innerHTML = svgs.stopwatch + ' 0:' + (rem<0?0:rem).toString().padStart(2,'0'); }
            if(prev.currentTime >= 30) stopPreview();
        }; 
    };
    
    window.voteSong = (id, title) => { 
        const f = new FormData(); 
        f.append('action', 'crjb_vote'); f.append('song_id', id); f.append('security', securityNonce); f.append('station', stationId);
        fetch(ajaxUrl, { method: 'POST', body: f }).then(r => r.json()).then(d => { 
            if(!d.success) showNotification(d.data, 'danger'); 
            else { 
                addVotedSong(id); trackJukeboxEvent('Vote Track', title); 
                showNotification('Vote added!', 'success'); 
                poll(); loadCat(); 
            }
        }).catch(e => showNotification('Cannot vote offline.', 'warning')); 
    };
    
    poll(); loadCat(); setInterval(loadCat, 60000); setInterval(poll, 5000);
    document.getElementById('crjb-catalog-sort').onchange = renderCat;
});
