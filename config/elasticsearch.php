<?php
/**
 *
 * ElasticSearch config
 */
return [
    'host'         => env('ELASTICSEARCH.ES_HOST', ''),
    'port'         => env('ELASTICSEARCH.ES_PORT', ''),
    'scheme'    => env('ELASTICSEARCH.ES_SCHEME', ''),
    'user'       => env('ELASTICSEARCH.ES_USER', ''),
    'pass'       => env('ELASTICSEARCH.ES_PASSWORD', ''),
];
