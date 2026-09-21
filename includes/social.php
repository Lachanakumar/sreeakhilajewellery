<?php
/**
 * Social network registry.
 *
 * One list, read by both Admin -> Settings -> Social (which builds its fields
 * from it) and the footer (which draws its icons from it). They used to be two
 * separate lists, which is how the admin ended up with a YouTube URL field that
 * no template ever rendered: filling it in changed nothing on the site.
 *
 * To add a network: add one entry below. The settings field and the footer icon
 * both appear, and they cannot drift apart again.
 *
 * Each key maps to the `social_<key>` settings row.
 */

require_once __DIR__ . '/functions.php';

function social_networks() {
    return [
        'facebook' => [
            'label'       => 'Facebook',
            'field'       => 'Facebook URL',
            'placeholder' => 'https://facebook.com/yourpage',
            'svg'         => '<svg xmlns="http://www.w3.org/2000/svg" width="7.667" height="16.524" viewBox="0 0 7.667 16.524"><path data-name="Path 237" d="M967.495,353.678h-2.3v8.253h-3.437v-8.253H960.13V350.77h1.624v-1.888a4.087,4.087,0,0,1,.264-1.492,2.9,2.9,0,0,1,1.039-1.379,3.626,3.626,0,0,1,2.153-.6l2.549.019v2.833h-1.851a.732.732,0,0,0-.472.151.8.8,0,0,0-.246.642v1.719H967.8Z" transform="translate(-960.13 -345.407)" fill="currentColor" /></svg>',
        ],
        'instagram' => [
            'label'       => 'Instagram',
            'field'       => 'Instagram URL',
            'placeholder' => 'https://instagram.com/yourhandle',
            'svg'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16.497" height="16.492" viewBox="0 0 19.497 19.492"><path data-name="Icon awesome-instagram" d="M9.747,6.24a5,5,0,1,0,5,5A4.99,4.99,0,0,0,9.747,6.24Zm0,8.247A3.249,3.249,0,1,1,13,11.238a3.255,3.255,0,0,1-3.249,3.249Zm6.368-8.451A1.166,1.166,0,1,1,14.949,4.87,1.163,1.163,0,0,1,16.115,6.036Zm3.31,1.183A5.769,5.769,0,0,0,17.85,3.135,5.807,5.807,0,0,0,13.766,1.56c-1.609-.091-6.433-.091-8.042,0A5.8,5.8,0,0,0,1.64,3.13,5.788,5.788,0,0,0,.065,7.215c-.091,1.609-.091,6.433,0,8.042A5.769,5.769,0,0,0,1.64,19.341a5.814,5.814,0,0,0,4.084,1.575c1.609.091,6.433.091,8.042,0a5.769,5.769,0,0,0,4.084-1.575,5.807,5.807,0,0,0,1.575-4.084c.091-1.609.091-6.429,0-8.038Zm-2.079,9.765a3.289,3.289,0,0,1-1.853,1.853c-1.283.509-4.328.391-5.746.391S5.28,19.341,4,18.837a3.289,3.289,0,0,1-1.853-1.853c-.509-1.283-.391-4.328-.391-5.746s-.113-4.467.391-5.746A3.289,3.289,0,0,1,4,3.639c1.283-.509,4.328-.391,5.746-.391s4.467-.113,5.746.391a3.289,3.289,0,0,1,1.853,1.853c.509,1.283.391,4.328.391,5.746S17.855,15.705,17.346,16.984Z" transform="translate(0.004 -1.492)" fill="currentColor" /></svg>',
        ],
        'whatsapp' => [
            'label'       => 'WhatsApp',
            'field'       => 'WhatsApp link (wa.me/91XXXXXXXXXX)',
            'placeholder' => 'https://wa.me/919XXXXXXXXX',
            'svg'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z" fill="currentColor" /></svg>',
        ],
        'youtube' => [
            'label'       => 'YouTube',
            'field'       => 'YouTube URL',
            'placeholder' => 'https://youtube.com/@yourchannel',
            'svg'         => '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="currentColor" /></svg>',
        ],
        'twitter' => [
            'label'       => 'X (Twitter)',
            'field'       => 'X / Twitter URL',
            'placeholder' => 'https://x.com/yourhandle',
            'svg'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="currentColor" /></svg>',
        ],
    ];
}

/**
 * The networks that actually have a URL configured, ready to render.
 *
 * A bare handle or a domain without a scheme still produces a working link, so
 * an admin typing "instagram.com/shop" does not end up with a relative URL that
 * resolves against the storefront.
 *
 * @return array<int, array{key: string, label: string, url: string, svg: string}>
 */
function social_links() {
    $out = [];
    foreach (social_networks() as $key => $meta) {
        $url = trim((string) get_setting('social_' . $key, ''));

        /* WhatsApp is usually already configured for the floating contact
           button, so build the footer link from that number rather than making
           someone type the same thing twice. An explicit social_whatsapp still
           wins if they want a different one. */
        if ($url === '' && $key === 'whatsapp') {
            $digits = preg_replace('/\D+/', '', (string) get_setting('whatsapp_number', ''));
            if ($digits !== '') {
                $url = 'https://wa.me/' . $digits;
            }
        }

        if ($url === '') {
            continue;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $out[] = [
            'key'   => $key,
            'label' => $meta['label'],
            'url'   => $url,
            'svg'   => $meta['svg'],
        ];
    }
    return $out;
}
