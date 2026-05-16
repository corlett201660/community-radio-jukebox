=== Community Radio Jukebox ===
Contributors: Gemini A.I. and Brandon Corlett
Tags: audio, jukebox, synchronized radio, offline player, music
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 4.46.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive, synchronized community radio engine with bulletproof offline progression and visual vote tracking.

== Description ==

Community Radio Jukebox is a sophisticated WordPress-based audio engine designed to transform static music libraries into an interactive, synchronized "broadcast" experience. It enforces a global "master clock," ensuring every listener—regardless of geographical location—hears the exact same beat at the same time.

Major features include:

* **Master Clock Synchronization:** Replaces expensive Icecast/Shoutcast streaming servers with decentralized, browser-based drift-correction. Everyone stays on the same second.
* **The "Unkillable" Offline Engine:** Silently utilizes the browser's Cache API to buffer upcoming tracks in the background. If a listener's Wi-Fi drops, the Jukebox seamlessly switches to local blobs and continues the broadcast without skipping a beat.
* **Cold-Start Recovery:** Hard refresh while completely offline? The app reconstructs your catalog from local memory and kicks off an Offline Radio fallback mode to keep the party going.
* **Audio Interruption Resilience:** Natively handles iOS/Android phone calls and background suspensions, allowing users to seamlessly resume sync from their lock screens.
* **Democratic Queue:** Gives listeners 10 votes per hour to boost their favorite tracks, complete with visual checkmark indicators stored securely in their browser.
* **Broadcast Analytics:** Automatically logs active listener counts and timestamps, and provides a 1-click CSV export of your recent broadcast history.
* **Audio-Reactive Visualizer:** Features a high-performance Three.js 3D starfield that reacts to the live frequency data of the music.

== Installation ==

1. Upload the entire `community-radio-jukebox` folder to the `/wp-content/plugins/` directory, or install the ZIP file directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Jukebox Songs > Add New Song** to start uploading your MP3s.
4. Place the shortcode `[local_jukebox_app]` on any page or post where you want the Jukebox to appear.
5. Make sure you have placed the required asset files inside your plugin's `/assets/css/` and `/assets/js/` directories for the frontend interface to render correctly.

== Frequently Asked Questions ==

= Why did the music stop playing in the background on my iPhone? =
iOS has incredibly strict rules regarding background audio execution. To keep the music playing, ensure you do not force-close the browser. If interrupted by a phone call, simply press "Play" on your lock screen to resume your connection to the live broadcast.

= What happens if the internet goes down at my venue? =
The Jukebox is designed as an offline-first Progressive Web App (PWA). It actively buffers upcoming songs and caches your library. If the connection fails, it will seamlessly fall back to the cached files and activate "Offline Radio" mode until the connection is restored.

= How do I export my broadcast logs? =
Navigate to **Jukebox Songs > Settings** in your WordPress dashboard. Under the "Broadcast Log Export" section, click the download button to receive a CSV file detailing the songs played over the last hour, alongside active listener counts.

== Changelog ==

= 4.15.2 =
* Fix: Adjusted CSV export output to satisfy WPCS escaping requirements without breaking spreadsheet formatting.

= 4.15.1 =
* Security: Implemented strict data unslashing and sanitization across all AJAX endpoints.
* Security: Added frontend nonces to secure the voting and polling systems.
* Performance: Removed external CDN dependencies. Enqueued local assets for Bootstrap, FontAwesome, and Three.js.
* Core: Replaced native PHP file system calls with output buffering for CSV generation.

= 4.15.0 =
* Core: Completely removed Gemini AI metadata generation dependencies.

= 4.14.0 =
* Feature: Added Broadcast Log export system with active listener count tracking.

= 4.13.0 =
* Feature: Implemented Cold-Start Recovery for offline hard-refreshes.
* Fix: Added robust local memory for catalog persistence.

= 4.12.0 =
* Feature: Integrated Native OS MediaSession actions for lock-screen control and interruption recovery.