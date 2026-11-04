<?php

namespace App\Constants;

use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Headers based on IANA Message Headers Registry and Wikipedia list.
 *
 * Class Constants
 */
final class Constants extends Response
{
    /*
    |--------------------------------------------------------------------------
    | Entity Status
    |--------------------------------------------------------------------------
    */

    /**
     * @var string
     */
    public const string STATUS_ACTIVE = 'Active';

    /**
     * @var string
     */
    public const string STATUS_INACTIVE = 'Inactive';

    /**
     * @var string
     */
    public const string STATUS_ALL = 'all';


    /*
    |--------------------------------------------------------------------------
    | Typographical Symbols
    |--------------------------------------------------------------------------
    */

    /**
     * @var string
     */
    public const string BACK_SLASH = '\\';

    /**
     * @var string
     */
    public const string SLASH = '/';

    /**
     * @var string
     */
    public const string UNDERSCORE = '_';

    /**
     * @var string
     */
    public const string HYPHEN = '-';

    /**
     * @var string
     */
    public const string AT_SIGN = '@';

    /**
     * @var string
     */
    public const string SPACE_BETWEEN = ' ';

    /*
    |--------------------------------------------------------------------------
    | File and Directory Modes
    |--------------------------------------------------------------------------
    */

    /**
     * @var int
     */
    public const int FILE_READ_MODE = 0644;

    /**
     * @var int
     */
    public const int FILE_WRITE_MODE = 0644;

    /**
     * @var int
     */
    public const int DIR_READ_MODE = 0755;

    /**
     * @var int
     */
    public const int DIR_WRITE_MODE = 0755;

    /**
     * @var string
     */
    public const string INVOICE_LOGO_PATH = "images/tour-operator/logo/";

    /*
    |--------------------------------------------------------------------------
    | General Status Code
    |--------------------------------------------------------------------------
    */

    /**
     * @var int
     */
    public const int STATUS_ZERO = 0;

    /**
     * @var int
     */
    public const int STATUS_ONE = 1;

    /**
     * @var int
     */
    public const int STATUS_TWO = 2;

    /**
     * @var int
     */
    public const int STATUS_THREE = 3;

    /**
     * @var int
     */
    public const int STATUS_FOUR = 4;

    /**
     * @var bool
     */
    public const bool STATUS_TRUE = true;

    /**
     * @var bool
     */
    public const bool STATUS_FALSE = false;

    /*
    |--------------------------------------------------------------------------
    | PAGINATION CONSTANTS
    |--------------------------------------------------------------------------
    */

    /**
     * @var int
     */
    public const int PAGINATION_LIMIT = 10;

    /*
    |--------------------------------------------------------------------------
    | CUSTOM CONSTANTS
    |--------------------------------------------------------------------------
    */

    /**
     * @var string
     */
    public const string USER_TYPE_TOUR_OPERATOR = "Tour Operator";

    /*
    |--------------------------------------------------------------------------
    | REQUESTS FROM CONSTANTS
    |--------------------------------------------------------------------------
    */

    /**
     * @var string
     */
    public const string BACKEND = "BACKEND";

    /**
     * @var string
     */
    public const string FRONTEND = "FRONTEND";

    /**
     * @var string
     */
    public const string ONLINE = "ONLINE";

    /**
     * Tour Self Check in api key
     * @var string
     */
    public const string TOUR_CHECK_IN_API_KEY = "dbab4df9-bfdf-426c-b239-b700ccb089b9";

    /**
     * SMS API Confg
     * @var string
     */
    public const string SMS_API_KEY = "EsBQ9oTikvad9WEBYZvHT2uCvCLpawpe5SjdL1WF";
    public const string SMS_API_URL = "https://confirm.tours";

    /**
     * @var string
     */
    public const string API = "API";
    public const string POS = "POS";

    public const string TRIPADVISOR = "TRIPADVISOR";

    public const string GETYOURGUIDE = "GETYOURGUIDE";

    public const string VIATOR_API_KEY = "59bb2e59-8428-49d0-bc25-41837660ddfb";

    public const string VIATOR_ACCOUNT_ID = "P00107674";

    public const string VIATOR_MCID = "42383";

    public const string VIATOR_MEDIUM = "api";

    public const string VIATOR_COMMON_URL = "https://www.viator.com";

    public const string DIXIES_TENANT_PROD_URL = "https://dixies.nativeamericantours.com/";

    public const string ADDONS_TICKET_REFUND = "ticket_refund";
    public const string ADDONS_PARTIAL_REFUND = "partial_refund";
    public const string ADDONS_FULL_REFUND = "full_refund";
    public const string ADDONS_DONT_REFUND = "dont_refund";

    public const int GROUP_ID = 1000;
    public const string TENANT_GCW = "gcw";

    public const int HTTP_SERVER_ERROR = 500;

    public const int OTA_REFERENCE_ID = 1000;
}
