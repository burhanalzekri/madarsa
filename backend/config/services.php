<?php
return [
 'cloudflare_stream'=>['account_id'=>env('CLOUDFLARE_ACCOUNT_ID'),'api_token'=>env('CLOUDFLARE_STREAM_TOKEN'),'customer_subdomain'=>env('CLOUDFLARE_STREAM_SUBDOMAIN')],
 'bigbluebutton'=>['url'=>env('BBB_URL'),'secret'=>env('BBB_SECRET')],
];
