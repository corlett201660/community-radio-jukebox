=== Community Radio Jukebox ===
Contributors: corlett201660
Tags: audio, radio, player, music, gemini
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.60.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive, synchronized community radio engine featuring Gemini AI curation, smart room routing, and bulletproof offline playback.

== Description ==

NOTE: REQUIRES BOOTSTRAP COMPATABLE THEME and the Font Awesome Plugin 
https://them.es/starter-bootstrap/
https://wordpress.org/plugins/font-awesome/

Community Radio Jukebox is a sophisticated WordPress-based audio engine designed to transform static music libraries into an interactive, synchronized "broadcast" experience for hospitality venues, bars, and community spaces. It enforces a global "master clock," ensuring every listener—regardless of geographical location or device—hears the exact same beat at the same time. 

Major features include:

* **Gemini 2.5 Pro AI Curator:** Forget manual data entry. Upload an MP3 and our integrated AI will physically "listen" to the audio to instantly assign strict musical genres and completely transcribe the lyrics.
* **Smart Station Routing:** Use simple shortcodes to create isolated jukeboxes for different rooms. Play Acoustic music on the patio, Rock in the main bar, or dedicate an entire queue to a specific artist.
* **Automated Schedule Takeovers:** Design your venue's timeline. Automatically transition the jukebox to "Friday Happy Hour" or "Late Night EDM," temporarily locking the request catalog to specific tags.
* **Master Clock Synchronization:** Replaces expensive Icecast/Shoutcast streaming servers with decentralized, browser-based drift-correction. Everyone stays on the same second.
* **The "Unkillable" Offline Engine:** Silently utilizes the browser's Cache API to buffer upcoming tracks in the background. If a patron's Wi-Fi drops, the Jukebox seamlessly switches to local files without skipping a beat.
* **Democratic Queue & Predictive UI:** Gives listeners 10 votes per hour to boost their favorite tracks. Advanced "Predictive Empty States" tell patrons exactly when a song will cool down or become available.
* **Venue Compliance & Safety:** Instantly filter out Explicit [E] tracks during family hours. Export a comprehensive 30-Day Broadcast Log (CSV) with one click to easily handle ASCAP, BMI, and SESAC royalty reporting.
* **Dedicated Lyric Pages:** Generates distraction-free track pages with full lyrics, metadata, and 30-second audio previews (or full-track playback for Royalty-Free music) to drive organic SEO traffic to your venue's site.

== External Services ==

This plugin relies on the Google Gemini API (Generative Language API) to provide automated audio analysis.

* **What the service is:** The Google Gemini API is an advanced multimodal artificial intelligence service.
* **What it is used for:** It is used strictly in the WordPress dashboard to analyze audio tracks, automatically assign musical genres, detect explicit content, and transcribe song lyrics.
* **What data is sent and when:** When a site administrator explicitly clicks the "✨ Analyze Audio" button or triggers a "Bulk Scan" from the Jukebox Settings page, the plugin encodes the specific MP3 audio file and its metadata (Title/Artist) and sends it securely to the Gemini API for analysis. No data is sent automatically or on the frontend; it strictly requires manual user initiation by an administrator.
* **Terms of Service:** https://ai.google.dev/terms
* **Privacy Policy:** https://policies.google.com/privacy

== Installation ==

1. Upload the entire `community-radio-jukebox` folder to the `/wp-content/plugins/` directory, or install the ZIP file directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Jukebox Songs > Settings** and input your Google Gemini API key to enable AI auto-tagging.
4. Navigate to **Jukebox Songs > Add New Song** to upload and scan your MP3s.
5. Place the shortcode `[community_radio_jukebox]` on any page or post where you want the main Jukebox to appear.
6. (Optional) Filter the jukebox by using parameters like `[community_radio_jukebox genre="jazz"]` or `[community_radio_jukebox artist="the-beatles"]`.

== Frequently Asked Questions ==

= How does the Gemini AI know the lyrics? =
We use the Gemini 2.5 Pro multimodal API. When you click "✨ Analyze Audio", the plugin securely encodes your MP3 file and sends the raw audio data to Google. Gemini physically "listens" to the file, identifies the genre, transcribes the lyrics, and saves it to your WordPress database.

= Do patrons need to download an app to vote? =
No. App Store friction ruins engagement. The Jukebox operates entirely as a Progressive Web App (PWA) within Safari or Chrome. Patrons scan a QR code on their table and can immediately vote with zero login required.

= What happens if the internet goes down at my venue? =
The Jukebox is designed as an offline-first application. It actively buffers upcoming songs and caches your library. If the connection fails, it will seamlessly fall back to the cached files and activate "Offline Mode" until the connection is restored.

= Can I use this with a live DJ? =
Yes! The Jukebox is the perfect digital "Request Line." Have the DJ open the app on a muted iPad in the booth to watch the queue update in real-time and gauge the room's mood. If the DJ needs to take a break, they simply fade up the Jukebox channel, and the Auto-DJ instantly takes over.

= How do I export my broadcast logs for ASCAP/BMI? =
Navigate to **Jukebox Songs > Settings** in your WordPress dashboard. Under the "Broadcast Log Export" section, click the download button to receive a massive 30-Day CSV file detailing every song played across all your stations.

== Changelog ==

= 4.52.0 =
* Security: Patched Database Denial of Service (DoS) vulnerability in AJAX station parameter routing.
* Security: Applied strict `esc_html()` sanitization to dedicated track headers to prevent XSS.
* Performance: Fixed Session Deadlocking concurrency issues allowing hundreds of simultaneous patron votes without server hang.

= 4.51.0 =
* Fix: Custom scrolling HTML banners are now properly hidden while previewing local tracks to prevent UI mismatch.

= 4.50.0 =
* Feature: Added "Royalty Free" licensing toggle for tracks.
* UI: Dedicated track pages now bypass the 30-second restriction and play the full track if marked as Royalty Free.

= 4.49.0 =
* Feature: Added Custom Scrolling Banners. Venues can now inject custom HTML messages (e.g., "Happy Birthday!" or drink specials) that side-scroll beneath the Now Playing track.

= 4.48.0 =
* Feature: Added 30-Second Previews to dedicated track pages, enforced via lightweight frontend JavaScript.

= 4.47.0 =
* Feature: Implemented "Auto-DJ Flush Prediction" (Predictive Empty States) to accurately tell patrons exactly when a track will be available to request.

= 4.46.0 =
* Security: Fixed critical SSRF vulnerability by replacing `wp_remote_get` with `wp_safe_remote_get` in the Gemini AI audio fetcher.
* Security: Added DOM-based XSS sanitization (`escHTML`) to all frontend queued tracks.

= 4.45.0 =
* Feature: Expanded Broadcast Log memory from 1 hour to a full 30 Days to satisfy ASCAP/BMI/SESAC royalty reporting requirements.

= 4.44.0 =
* Feature: Re-integrated AI. Upgraded architecture to utilize Gemini 2.5 Pro for audio-to-text genre tagging and lyric transcription.
* Fix: Added "Safety Catch" database sync protection to the Analyze Audio button to prevent race conditions.

= 4.15.2 =
* Fix: Adjusted CSV export output to satisfy WPCS escaping requirements without breaking spreadsheet formatting.
