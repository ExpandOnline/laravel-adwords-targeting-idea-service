<?php

namespace SchulzeFelix\AdWords\Responses;

use SchulzeFelix\DataTransferObject\DataTransferObject;

class Keyword extends DataTransferObject
{
    protected $casts = [
        'keyword'                   => 'string',
        'search_volume'             => 'integer',
        'cpc'                       => 'float',
        'competition'               => 'string',
        'targeted_monthly_searches' => 'collection',
        'high_top_of_page_bid' => 'float',
	'low_top_of_page_bid' => 'float'
   ];
}

