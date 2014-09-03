FORMAT: 1A

# WooCommerce v2 REST API

Introduced in WooCommerce 2.1, the REST API allows store data to be created, read, updated, and deleted using the JSON format.

## Requirements

You must be using WooCommerce 2.1 or newer and the REST API must be enabled under `WooCommerce > Settings`. You must enable pretty permalinks, as default permalinks will not work.

## Schema

The API is accessible via this endpoint:

`https://www.your-store.com/wc-api/v2`

You may access the API over either HTTP or HTTPS. HTTPS is recommended where possible, as authentication is simpler. The API index will declare if the site supports SSL or not.

## Version

The current API version is `v2` which takes a first-order position in endpoints. The `v1` endpoint is available in WooCommerce 2.1/2.2, but it will be removed in a future version.

### Differences between v1 and v2 versions

* v1 supports XML response format, v2 only supports JSON.
* v1 does not support creating or updating (with the exception of order status) any resources, v2 supports full create/read/update/delete for all endpoints.
* v1 does not include order item meta, v2 includes full order item meta (with an optional `filter` parameter to include protected order item meta)
* v1 does not include any endpoints for listing a customer's available downloads, v2 includes the `GET /customer/{id}/downloads` endpoint.
* v1 includes an endpoint for listing notes for an order, v2 includes full create/read/update/delete endpoints.
* v1 does not include any endpoints for listing product categories, v2 includes two endpoints for product categories (`GET /products/categories` and `GET /products/categories/{id}`).
* v1 does not include any endpoints for getting valid order statuses, v2 includes an endpoint for listing valid order statuses (`GET /orders/statuses`).
* v2 supports the core features added in WooCommerce 2.2, primarily order refunds (via the `/orders/refunds` endpoint) and Webhooks (via the `/webhooks`).

## Requests/Responses

The default response format is JSON. Requests with a message-body use plain JSON to set or update resource attributes. Successful requests will return a `200 OK` HTTP status.

Some general information about responses:

* Dates are returned in [RFC3339](http://www.ietf.org/rfc/rfc3339.txt) format in UTC timezone: `YYYY-MM-DDTHH:MM:SSZ`

* Resource IDs are returned as integers.

* Any decimal monetary amount, such as prices or totals, are returned as strings with two decimal places. The decimal separator (typically either `.` or `,`) is controlled by the site and is included in the API index. This is by design, in order to make localization of API data easier for the client. You may need to account for this in your implemetation if you will be doing calculations with the returned data (e.g. convert string amounts with commas as the decimal place before performing any calculations)

* Other amounts, such as item counts, are returned as integers.

* Blank fields are generally included as `null` instead of being blank strings or omitted.

## Authentication

There are two aways to authenticate with the API, depending on whether the site supports SSL or not.  Remember that the Index endpoint will indicate if the site supports SSL or not.

### Over HTTPS

You may use [HTTP Basic Auth](http://en.wikipedia.org/wiki/Basic_access_authentication) by providing the API Consumer Key as the username and the API Consumer Secret as the password:

```
$ curl https://www.example.com/wc-api/v1/orders \
    -u consumer_key:consumer_secret
```

Occasionally some servers may not properly parse the Authorization header (if you see a "Consumer key is missing" error when authenticating over SSL, you have a server issue). In this case, you may provide the consumer key/secret as query string parameters:

```
$ curl https://www.example.com/wc-api/v1/orders?consumer_key=123&consumer_secret=abc
```

### Over HTTP
You must use [OAuth 1.0a "one-legged" authentication](http://tools.ietf.org/html/rfc5849) to ensure API credentials cannot be intercepted. Typically you may use any standard OAuth 1.0a library in your language of choice to handle the authentication, or generate the necessary parameters by following these instructions.

#### Generating an OAuth signature

1) Set the HTTP method for the request:

`GET`

2) Set your base request URI -- this is the full request URI without query string parameters -- and URL encode according to RFC 3986:

```
http://www.example.com/wc-api/v1/orders
```

when encoded:

```
http%3A%2F%2Fwww.example.com%2Fwc-api%2Fv1%2Forders
```

3) Collect and normalize your query string parameters. This includes all `oauth_*` parameters except for the signature. Parameters should be normalized by URL encoding according to RFC 3986 (`rawurlencode` in PHP) and percent(`%`) characters should be double-encoded (e.g. `%` becomes `%25`.

4) Sort the parameters in byte-order (`uksort( $params, 'strcmp' )` in PHP)

5) Join each parameter with an encoded equals sign (`%3D`):

`oauth_signature_method%3DHMAC-SHA1`

6) Join each parameter key/value with an encoded ampersand (`%26`):

`oauth_consumer_key%3Dabc123%26oauth_signature_method%3DHMAC-SHA1`

7) Form the string to sign by joining the HTTP method, encoded base request URI, and encoded parameter string with an unencoded ampersand symbol (&):

`GET&http%3A%2F%2Fwww.example.com%2Fwc-api%2Fv1%2Forders&oauth_consumer_key%3Dabc123%26oauth_signature_method%3DHMAC-SHA1`

8) Generate the signature using the string to key and your consumer secret key

If you are having trouble generating a correct signature, you'll want to review your string to sign for errors with encoding. The [authentication source](https://github.com/woothemes/woocommerce/blob/master/includes/api/class-wc-api-authentication.php#L177) can also be helpful in understanding how to properly generate the signature.

#### OAuth Tips

* The OAuth parameters must be added as query string parameters and *not* included in the Authorization header. This is because there is no reliable cross-platform way to get the raw request headers in WordPress.

* The require parameters are: `oauth_consumer_key`, `oauth_timestamp`, `oauth_nonce`, `oauth_signature`, and `oauth_signature_method`. `oauth_version` is not required and must be omitted.

* HMAC-SHA1 or HMAC-SHA256 are the only accepted hash algorithms.

* The OAuth nonce can be any randomly generated 32 character (recommended) string that is unique to the consumer key. Read more suggestions on [generating a nonce](https://dev.twitter.com/discussions/12445) on the Twitter API forums.

* The OAuth timestamp should be the unix timestamp at the time of the request. The API will deny any requests that include a timestamp that is outside of a 15 minute window to prevent replay attacks.

* You must use the store URL provided by the index when forming the base string used for the signature, as this is what the server will use. (e.g. if the store URL includes a `www` sub-domain, you should use it for requests)

* Some OAuth libraries add an ampersand to the provided secret key before generating the signature. This does not adhere to the OAuth spec and the ampersand should be removed prior to generating the signature.

* You may test your generated signature using LinkedIn's [OAuth test console](http://developer.linkedin.com/oauth-test-console) -- leave the member token/secret blank.

* Twitter has great instructions on [generating a signature](https://dev.twitter.com/docs/auth/creating-signature) with OAuth 1.0a, but remember tokens are not used with this implementation.

* Note that the request body is *not* signed as per the OAuth spec, see [Google's OAuth 1.0 extension](https://oauth.googlecode.com/svn/spec/ext/body_hash/1.0/oauth-bodyhash.html) for details on why.

## Parameters

All endpoints accept optional parameters which can be passed as an HTTP query string parameter, e.g. `GET /orders?status=completed`. There are common parameters and endpoint-specific parameters which are documented along with that endpoint.

### Filter Parameter

All endpoints accept a `filter` parameter that scopes individual filters using brackets, like date filtering:

`GET /orders?filter[created_at_min]=2013-11-01`

Multiple `filter` parameters can be included and intermixed with other parameters:

`GET /orders?status=completed&filter[created_at_min]=2013-11-01&filter[created_at_max]=2013-11-30`

Note that the following filters are supported for all endpoints except the `reports` endpoint, which has it's own set of filters that are documented along with that endpoint.

#### Available Filters
* `created_at_min` - given a date, only resources *created after the provided date* will be returned.
* `created_at_max` - given a date, only resources *created before the provided date* will be returned.
* `updated_at_min` - given a date, only resources *updated after the provided date* will be returned.
* `updated_at_max` - given a date, only resources *updated before the provided date* will be returned.
* `q` - performs a keyword search and returns resources that match, e.g. `GET /products?filter[q]=search-keyword`. Note that search terms should be URL-encoded as they will be decoded internally with [`urldecode`](http://us3.php.net/manual/en/function.urldecode.php)
* `order` - controls the ordering of the resources returned, accepted values are `ASC` (default) or `DESC`.
* `orderby` - controls the field that is used for ordering the resources returned. Accepts the same arguments as [`WP_Query`](http://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters). Defaults to `date`. You can order by `meta_value` but you must provide `orderby_meta_key`.
* `orderby_meta_key` - the meta key to order returned resources by when using `orderby=meta_value`. For example, you could order products by price using `GET /products?filter[orderby]=meta_value&filter[orderby_meta_key]=_price`
* `post_status` - limits resources to only those with the specified post status. Most useful for returning unpublished products, e.g. `GET /products?filter[post_status]=draft`
* `meta` - resource meta is excluded by default, but it can be included by setting `meta=true`, e.g. `GET /orders?filter[meta]=true`. Protected meta (meta whose key is prefixed with an underscore) is not included in the response.
* Pagination - explained below.

Note that Dates should be provided in [RFC3339](http://www.ietf.org/rfc/rfc3339.txt) format in UTC timezone: `YYYY-MM-DDTHH:MM:SSZ`. You may omit the time and timezone if desired.

### Fields Parameter

You may limit the fields returned in the response using the `fields` parameter:

`GET /orders?fields=id`

To include multiple fields, separate them with commas:

`GET /orders?fields=id,status`

You can specify sub-fields using dot-notation:

`GET /orders?fields=id,status,payment_details.method_title`

Sub-fields can't be limited for resources that have multiple structs, like an order's line items. For example, this will return just the line items, but each line item will have the full set of information, not just the product ID:

`GET /orders?fields=line_items.product_id`

## Pagination

Requests that return multiple items will be paginated to 10 items by default. This default can be changed by the site administrator by changing the `posts_per_page` option. Alternatively the items per page can be specifed with the `?filter[limit]` parameter:

`GET /orders?filter[limit]=15`

You can specify further pages with the `?page` parameter:

`GET /orders?page=2`

You may also specify the offset from the first resource using the `?filter[offset]` parameter:

`GET /orders?filter[offset]=5`

Page number is 1-based and ommiting the `?page` parameter will return the first page.

The total number of resources and pages are always included in the `X-WC-Total` and `X-WC-TotalPages` HTTP headers.

## Link Header

Pagination info is included in the [Link Header](http://tools.ietf.org/html/rfc5988). It's recommended that you follow these values instead of building your own URLs where possible.

```
Link: <https://www.example.com/wc-api/v1/products?page=2>; rel="next",
<https://www.example.com/wc-api/v1/products?page=3>; rel="last"`
```

*Linebreak included for readability*

The possible `rel` values are:

* `next` - Shows the URL of the immediate next page of results
* `last` - Shows the URL of the last page of results
* `first` - Shows the URL of the first page of results
* `prev` - Shows the URL of the immediate previous page of results

## Errors

Occasionally you might encounter errors when accessing the API. There are four possible types:

* Invalid requests, such as using an unsupported HTTP method will result in `400 Bad Request`:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_unsupported_method",
      "message" : "Unsupported request method"
    }
  ]
}
```

* Authentication or permission errors, such as incorrect API keys will result in `401 Unauthorized`:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_authentication_error",
      "message" : "Consumer Key is invalid"
    }
  ]
}
```

* Requests to resources that don't exist or are missing required parameters will result in `404 Not Found`:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_invalid_order",
      "message" : "Invalid order"
    }
  ]
}
```

* Requests that cannot be processed due to a server error will result in `500 Internal Server Error`:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_invalid_handler",
      "message" : "The handler for the route is invalid"
    }
  ]
}
```

Errors return both an appropriate HTTP status code and response object which contains a `code` and `message` attribute. If an endpoint has any custom errors, they are documented with that endpoint.

## HTTP Verbs

The API uses the appropriate HTTP verb for each action:

* `HEAD` - Can be used for any endpoint to return just the HTTP header information
*  `GET` - Used for retrieving resources
*  `PUT` - Used for updating resources
*  `POST` - Used for creating resources
*  `DELETE` - Used for deleting resources

## JSONP Support

The API supports JSONP by default. JSONP responses uses the `application/javascript` content-type. You can specify the callback using the `?_jsonp` parameter for `GET` requests to have the response wrapped in a JSON function:

```
GET /orders/count?_jsonp=ordersCount

\**\ordersCount({"count":8})
```

If the site administrator has chosen to disable it, you will receive a`400 Bad Request` error:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_jsonp_disabled",
      "message" : "JSONP support is disabled on this site"
    }
  ]
}
```
If your callback contains invalid characters, you will receive a `400 Bad Request` error:

```
{
  "errors" : [
    {
      "code" : "woocommerce_api_jsonp_callback_invalid",
      "message" : "The JSONP callback function is invalid"
    }
  ]
}
```

## Webhooks

Webhooks are an experimental feature in the v2 REST API. They must be managed using the REST API endpoints as a UI is not yet available. The `WC_Webhook` class manages all data storage/retrieval from the custom post type, as well as enqueuing a webhook's actions and processing/delivering/logging the webhook. On `woocommerce_init`, active webhooks are loaded and their associated hooks are added.

Each webhook has:

* status: active (delivers payload), paused (delivery paused by admin), disabled (delivery paused by failure)
* topic: determines which resource events the webhook is triggered for
* delivery URL: URL where the payload is delivered, must be HTTP or HTTPS
* secret: an optional secret key that is used to generate a HMAC-SHA256 hash of the request body so the receiver can verify authenticity of the webhook
* hooks: an array of hook names that are added and bound to the webhook for processing

### Topics

The topic is a combination resource (e.g. order) and event (e.g. created) and maps to one or more hook names (e.g. `woocommerce_checkout_order_processed`). Webhooks can be created using the topic name and the appropriate hooks are automatically added.

Core topics are:

* `coupon.created, coupon.updated, coupon.deleted`
* `customer.created, customer.updated, customer.deleted`
* `order.created, order.updated, order.deleted`
* `product.created, product.updated, product.deleted`

Custom topics can also be used which map to a single hook name, so for example you could add a webhook with topic `action.woocommerce_add_to_cart` that is triggered on that event. Custom topics pass the first hook argument to the payload, so in this example the `cart_item_key` would be included in the payload.

### Delivery/Payload

Delivery is done using `wp_remote_post()` (HTTP POST) and processed in the background by default using wp-cron. A few custom headers are added to the request to help the receiver process the webhook:

* `X-WC-Webhook-Topic` - e.g. `order.updated`
* `X-WC-Webhook-Resource` - e.g. `order`
* `X-WC-Webhook-Event` - e.g. `updated`
* `X-WC-Webhook-Signature` - a base64 encoded HMAC-SHA256 hash of the payload
* `X-WC-Webhook-ID` - webhook's post ID
* `X-WC-Delivery-ID` - delivery log ID (a comment)

The payload is JSON encoded and for API resources (coupons,customers,orders,products), the response is exactly the same as if requested via the REST API. 

### Logging

Requests/responses are logged as comments on the webhook custom post type. Each delivery log includes:

* Request duration
* Request URL, method, headers, and body
* Response Code, message, headers, and body

Only the 25 most recent delivery logs are kept in order to reduce comment table bloat.

After 5 consecutive failed deliveries (as defined by a non HTTP 2xx response code), the webhook is disabled and must be edited via the REST API to re-enable.

Delivery logs can be fetched through the REST API endpoint or in code using `WC_Webhook::get_delivery_logs()`

### Endpoints

See the webhook resource section.

## Troubleshooting

* Nginx - Older configurations of Nginx can cause issues with the API, see [this issue](https://github.com/woothemes/woocommerce/issues/5616#issuecomment-47338737) for details

## Tools

* [WooCommerce REST API Client Library](https://github.com/kloon/WooCommerce-REST-API-Client-Library) - A simple PHP client library by Gerhard Potgieter
* [CocoaRestClient](https://code.google.com/p/cocoa-rest-client/) - A free, easy to use Mac OS X GUI client for interacting with the API, most useful when your test store has SSL enabled.
* [Paw HTTP Client](https://itunes.apple.com/us/app/paw-http-client/id584653203?mt=12) - Another excellent HTTP client for Mac OS X

# Group API Index

The API index provides information about the endpoints available for the site, as well as store-specific information.

No authentication is required to access the API index, however if the REST API is disabled, you will receive a `404 Not Found` error.

## Index [/]

Store Attributes:

* `routes`: a list of available endpoints for the site keyed by relative URL. Each endpoint specifies the HTTP methods supported as well as the canonical URL.
* `dimension_unit`: the unit set for product dimensions. Valid units are `cm`, `m`, `cm`, `mm`, `in`, and `yd`
* `tax_included`: true if prices include tax, false otherwise
* `ssl_enabled`: true if SSL is enabled for the site, false otherwise
* `timezone`: the site's timezone
* `currency_format`: the currency symbol, HTML encoded
* `weight_unit`: the unit set for product weights. Valid units are `kg`, `g`, `lbs`, `oz`
* `description`: the site's description
* `name`: the name of the site
* `URL`: the site's URL
* `permalinks_enabled`: whether pretty permalinks are enabled on the site, if this is false, the API will not function correctly
* `wc_version`: the active WooCommerce version

+ Model (application/json)

    JSON representation of the Coupon resource.
    
    + Body
    
        ```json
        {
          "store": {
            "name": "Your Store Name",
            "description": "Your Store Description",
            "URL": "https://www.your-store.com",
            "wc_version": "2.2.0",
            "routes": {
              "\\/": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/"
                }
              },
              "\\/customers": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/customers"
                },
                "accepts_data": true
              },
              "\\/customers\\/count": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/customers/count"
                }
              },
              "\\/customers\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/customers\\/email\\/<email>": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/customers\\/<id>\\/orders": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/customers\\/<id>\\/downloads": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/orders": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/orders"
                },
                "accepts_data": true
              },
              "\\/orders\\/count": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/orders/count"
                }
              },
              "\\/orders\\/statuses": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/orders/statuses"
                }
              },
              "\\/orders\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/orders\\/<order_id>\\/notes": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "accepts_data": true
              },
              "\\/orders\\/<order_id>\\/notes\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/orders\\/<order_id>\\/refunds": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "accepts_data": true
              },
              "\\/orders\\/<order_id>\\/refunds\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/products": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/products"
                },
                "accepts_data": true
              },
              "\\/products\\/count": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/products/count"
                }
              },
              "\\/products\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/products\\/<id>\\/reviews": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/products\\/categories": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/products/categories"
                }
              },
              "\\/products\\/categories\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/coupons": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/coupons"
                },
                "accepts_data": true
              },
              "\\/coupons\\/count": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/coupons/count"
                }
              },
              "\\/coupons\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/coupons\\/code\\/<code>": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/reports": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/reports"
                }
              },
              "\\/reports\\/sales": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/reports/sales"
                }
              },
              "\\/reports\\/sales\\/top_sellers": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/reports/sales/top_sellers"
                }
              },
              "\\/webhooks": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/webhooks"
                },
                "accepts_data": true
              },
              "\\/webhooks\\/count": {
                "supports": [
                  "HEAD",
                  "GET"
                ],
                "meta": {
                  "self": "https://your-store.com/wc-api/v2/webhooks/count"
                }
              },
              "\\/webhooks\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET",
                  "POST",
                  "PUT",
                  "PATCH",
                  "DELETE"
                ],
                "accepts_data": true
              },
              "\\/webhooks\\/<webhook_id>\\/deliveries": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              },
              "\\/webhooks\\/<webhook_id>\\/deliveries\\/<id>": {
                "supports": [
                  "HEAD",
                  "GET"
                ]
              }
            },
            "meta": {
              "timezone": "America/New_York",
              "currency": "USD",
              "currency_format": "&#36;",
              "tax_included": false,
              "weight_unit": "g",
              "dimension_unit": "cm",
              "ssl_enabled": true,
              "permalinks_enabled": true,
              "links": {
                "help": "http://woothemes.github.io/woocommerce/rest-api/"
              }
            }
          }
        }
        ```

### Get the Index [GET]
+ Response 200
    
    [Index][]

# Group Coupons

## Coupon [/coupons/{id}{?force}]

The Coupon resource has the following attributes:

* `id` *(int)* - coupon ID (post ID)
* `code` *(string)* - coupon code, always lowercase.
* `type` *(string)* - coupon type, valid core types are:
* `created_at`
* `updated_at`
* `amount`
* `individual_use`
* `product_ids`
* `exclude_product_ids`
* `usage_limit`
* `usage_limit_per_user`
* `limit_usage_to_x_items`
* `usage_count`
* `expiry_date`
* `apply_before_tax`
* `enable_free_shipping`
* `product_category_ids`
* `exclude_product_category_ids`
* `exclude_sale_items`
* `minimum_amount`
* `maximum_amount`
* `customer_emails`
* `description`

+ Parameters
    + id (required, number, `123`) ... ID of the coupon

+ Model (application/json)
    
    + Body
    
        ```json
        {
          "coupon":{
            "id":21548,
            "code":"augustheat",
            "type":"fixed_cart",
            "created_at":"2014-08-30T19:25:48Z",
            "updated_at":"2014-08-30T19:25:48Z",
            "amount":"5.00",
            "individual_use":false,
            "product_ids":[
        
            ],
            "exclude_product_ids":[
        
            ],
            "usage_limit":null,
            "usage_limit_per_user":null,
            "limit_usage_to_x_items":0,
            "usage_count":0,
            "expiry_date":"2014-08-30T21:22:13Z",
            "apply_before_tax":true,
            "enable_free_shipping":false,
            "product_category_ids":[
        
            ],
            "exclude_product_category_ids":[
        
            ],
            "exclude_sale_items":false,
            "minimum_amount":"0.00",
            "maximum_amount":"0.00",
            "customer_emails":[
        
            ],
            "description":"Beat the August heat with $5 off your purchase!"
          }
        }
        ```

### Get a single Coupon [GET]
+ Response 200
    
    [Coupon][]

### Edit a Coupon [PUT]
To update a Coupon, send a JSON hash with an updated value for one or more of the Coupon resource attributes. All attributes values from the previous version of the Coupon are carried over by default if not included in the hash.

+ Request (application/json)
            
    + Body
    
        ```json
        {
          "coupon":{
            "code":"augustheat",
            "type":"fixed_cart",
            "amount":"5.00",
            "description":"Beat the August heat with $5 off your purchase!"
          }
        }
        ```

+ Response 200

    [Coupon][]

### Delete a Coupon [DELETE]

+ Parameters
    + force (optional, string `true`) ... Whether to permanently delete the coupon, defaults to `false`. Note that permanently deleting the coupon will return HTTP 200 rather than HTTP 202.

+ Response 202
    
    + Body

        ```json        
        {
            "message": "Deleted coupon"
        }
        ```
            
## Coupons Collection [/coupons{?fields,filter,page}]

Collection of all Coupons

+ Parameters
    * fields (optional, string, `id,code`) ... see Parameters > Fields Parameter documentation
    * filter (optional, string, `filter[limit]=100`) ... see Parameters > Filter Parameter documention
    * page (optional, string, `2`) ... see Pagination documentation

+ Model (application/json)

    + Body
        
        ```json
        {
          "coupons":[
            {
              "id":21548,
              "code":"mayshowers",
              "type":"fixed_cart",
              "created_at":"2014-08-30T19:25:48Z",
              "updated_at":"2014-08-30T19:25:48Z",
              "amount":"4.00",
              "individual_use":false,
              "product_ids":[
        
              ],
              "exclude_product_ids":[
        
              ],
              "usage_limit":null,
              "usage_limit_per_user":null,
              "limit_usage_to_x_items":0,
              "usage_count":0,
              "expiry_date":"2014-08-30T21:12:41Z",
              "apply_before_tax":true,
              "enable_free_shipping":false,
              "product_category_ids":[
        
              ],
              "exclude_product_category_ids":[
        
              ],
              "exclude_sale_items":false,
              "minimum_amount":"0.00",
              "maximum_amount":"0.00",
              "customer_emails":[
        
              ],
              "description":""
            },
            {
              "id":21541,
              "code":"summerfun",
              "type":"fixed_cart",
              "created_at":"2014-07-30T20:16:10Z",
              "updated_at":"2014-07-31T15:46:34Z",
              "amount":"0.00",
              "individual_use":false,
              "product_ids":[
        
              ],
              "exclude_product_ids":[
        
              ],
              "usage_limit":null,
              "usage_limit_per_user":null,
              "limit_usage_to_x_items":0,
              "usage_count":0,
              "expiry_date":"2014-08-30T21:12:41Z",
              "apply_before_tax":false,
              "enable_free_shipping":false,
              "product_category_ids":[
        
              ],
              "exclude_product_category_ids":[
        
              ],
              "exclude_sale_items":false,
              "minimum_amount":"0.00",
              "maximum_amount":"0.00",
              "customer_emails":[
        
              ],
              "description":""
            }
          ]
        }
        ```

### List all Coupons [GET]

+ Response 200

    [Coupons Collection][]
    
### Create a Coupon [POST]

To create a new Coupon provide a JSON hash. Required attributes are: TODO

+ Request (application/json)

    ```json
    {
      "coupon": {
        "code": "autumn-is-coming",
        "type": "fixed_cart",
        "amount": "4.00",
        "individual_use": true,
        "description": ""
      }
    }
    ```

+ Response 201 (application/json)

    [Coupon][]
    
# Group Webhooks

## Webhook [/webhooks/{id}]

The webhook resource has the following attributes:

* `id` *(int)* - the webhook ID (post ID)
* `name` *(string)* - a friendly name for the webhook, defaults to "Webhook created on <date>"
* `status` *(string)* - webhook status, options are `active` (delivers payload), `paused` (does not deliver)`, or `disabled` (does not deliver due delivery failures)
* `topic` *(string)* - webhook topic, e.g. `coupon.updated`
* `resource` *(string)* - webhook resource, e.g. `coupon`
* `event` *(string)* - webhook event, e.g. `updated`
* `hooks` *(hash)* - JSON hash of WordPress action names associated with the webhook
* `delivery_url` *(string)* - the URL where the webhook payload is delivered
* `created_at` *(string)* - UTC DateTime when the webhook was created
* `updated_at` *(string)* - UTC DateTime when the webhook was last updated

+ Parameters
    * id (required, number, `123`) ... ID of the webhook

+ Model (application/json)

    + Body

        ```json
        {
          "webhook": {
            "id": 21531,
            "name": "An add to cart webhook",
            "status": "active",
            "topic": "action.woocommerce_add_to_cart",
            "resource": "action",
            "event": "woocommerce_add_to_cart",
            "hooks": [
              "woocommerce_add_to_cart"
            ],
            "delivery_url": "http://requestb.in/1972vwx1",
            "created_at": "2014-07-30T16:53:12Z",
            "updated_at": "2014-07-30T16:53:12Z"
          }
        }

### Get a single webhook [GET]
+ Response 200

    [Webhook][]

### Edit a Webhook [PUT]

To update a webhook, send a JSON hash with an updated value for one of more of the Webhook attributes. All attributes values from the previous version of the Webhook are carried over by default if not included in the hash.

+ Request (application/json)

    + Body

        ```json
        {
          "webhook": {
            "topic": "An add to cart webhook"
            "delivery_url": "http://requestb.in/1972vwx1",
          }
        }
        ```

+ Response 200

    [Webhook][]

### Delete a Webhook [DELETE]

+ Response 202

    + Body

        ```json
        {
          "message": "Permanently deleted webhook"
        }
        ```

## Webhooks Collection [/webhooks{?fields,filter,page}]

A collection of webhooks

+ Model (application/json)

    + Body

        ```json
        {
        {
          "webhooks":[
            {
              "id":21551,
              "name":"Webhook created on Sep 03, 2014 @ 04:24 PM",
              "status":"active",
              "topic":"coupon.created",
              "resource":"coupon",
              "event":"created",
              "hooks":[
                "woocommerce_process_shop_coupon_meta",
                "woocommerce_api_create_coupon"
              ],
              "delivery_url":"http://postcatcher.in/catchers/53d951274c003202000005b8",
              "created_at":"2014-09-03T16:24:47Z",
              "updated_at":"2014-09-03T16:24:47Z"
            },
            {
              "id":21537,
              "name":"Webhook created on Jul 30, 2014 @ 08:10 PM",
              "status":"active",
              "topic":"order.created",
              "resource":"order",
              "event":"created",
              "hooks":[
                "woocommerce_checkout_order_processed",
                "woocommerce_process_shop_order_meta",
                "woocommerce_api_create_order"
              ],
              "delivery_url":"http://postcatcher.in/catchers/53d951274c003202000005b8",
              "created_at":"2014-07-30T20:10:36Z",
              "updated_at":"2014-07-30T20:10:36Z"
            }
          ]
        }
        ```

### List all Webhooks [GET]

+ Parameters
    * fields (optional, string, `id,name`) ... see Parameters > Fields Parameter documentation
    * filter (optional, string, `filter[limit]=100`) ... see Parameters > Filter Parameter documention
    * page (optional, string, `2`) ... see Pagination documentation

+ Response 200

    [Webhooks Collection][]

### Create a Webhook [POST]

To create a new Webhook, provide a JSON hash.

Required attributes are:

* `topic` *(string)* - a valid topic, see the complete list above under Webhooks
* `delivery_url` *(string)* - a valid delivery URL starting with `http://` or `https://`

Optional attributes are:

* `name` *(string)* - a friendly name for identifying this webhook, defaults to `Webhook created on <date>`
* `secret` *(string)* - a secret key used to generate a hash of the delivered webhook and provided in the request headers. This will default to the current API user's consumer secret if not provided.

Note that after successfully creating a new webhook, a ping is sent to the delivery URL containing the newly-created webhook's ID (e.g. `webhook_id=<id>`)

+ Request (application/json)

    + Body

        ```json
        {
          "webhook": {
            "name": "An add to cart webhook",
            "secret": "my-super-secret-private-key",
            "topic": "action.woocommerce_add_to_cart",
            "delivery_url": "http://requestb.in/1972vwx1"
          }
        }
        ```

+ Response 201 (application/json)

    [Webhook][]

## Webhook Count [/webhooks/count{?status,filter}]

### Get total count of Webhooks [GET]

Get the total number of Webhooks

+ Parameters
    * status (optional, string, `paused`) ... Get a total count of webhooks with the given status
    * filter (optional, string, `filter[created_at]=2014-09-01`) ... see Parameters > Filter Parameter documention
    
+ Response 200 (application/json)

    + Body

        ```json
        {
          "count": 3
        }
        ```

## Webhook Delivery [/webhooks/{webhook_id}/deliveries{id}{?fields}]

Delivery logs are saved whenever a webhook is delivered. Only the 25 most recent deliveries for a webhook are available. The webhook delivery has the following attributes:

* `id` *(int)* - the delivery ID (comment ID)
* `duration` *(string)* - the delivery duration, in seconds
* `summary` *(string)* - a friendly summary of the response including the HTTP response code, message, and body
+ `request_url` *(string)* - the URL where the webhook was delivered
+ `request_headers` *(hash)* - a JSON hash of request headers
    * `User-Agent` *(string)* - the request user agent, defaults to "WooCommerce/{version} Hookshot (WordPress/{version})"
    * `Content-Type` *(string)* - the request content-type, defaults to "application/json"
    * `X-WC-Webhook-Topic` *(string)* - the webhook topic
    * `X-WC-Webhook-Resource` *(string)* - the webhook resource
    * `X-WC-Webhook-Event` *(string)* - the webhook event
    * `X-WC-Webhook-Signature` *(string)* - a base64 encoded HMAC-SHA256 hash of the payload
    * `X-WC-Webhook-ID` *(int)* - the webhook's ID
    * `X-WC-Webhook-Delivery-ID` *(int)* - the delivery ID
+ `request_body` *(string)* - the request body, this matches the API response for the given resource (e.g. for the coupon.updated topic, the request body would match the response for GET /coupons/{id})
+ `response_code` *(string)* - the HTTP response code from the receiving server
+ `response_message` *(string)* - the HTTP response message from the receiving server
+ `response_headers` *(string)* - a JSON hash of the response headers from the receiving server
+ `response_body` *(string)* - the response body from the receiving server
+ `created_at` *(string)* - a DateTime of when the delivery was logged

+ Parameters
    * webhook_id (required, number, `123`) ... ID of the webhook
    * id (required, number, `789`) ... ID of the webhook delivery
    * fields (optional, string, `duration,summary`) ... see Parameters > Fields Parameter documentation

+ Model (application/json)

    + Body

        ```json
        {
          "webhook_delivery":{
            "id":88360,
            "duration":"0.09728",
            "summary":"HTTP 201 Created: Created",
            "request_method":"POST",
            "request_url":"http://postcatcher.in/catchers/53d943dc4c00320200000485",
            "request_headers":{
              "User-Agent":"WooCommerce/2.2.0 Hookshot (WordPress/3.9.1)",
              "Content-Type":"application/json",
              "X-WC-Webhook-Topic":"order.created",
              "X-WC-Webhook-Resource":"order",
              "X-WC-Webhook-Event":"created",
              "X-WC-Webhook-Signature":"JsiERts6QzRDhhb6uXJj3oD1m74jr1jAlOq6U4cQ33A=",
              "X-WC-Webhook-ID":21535,
              "X-WC-Webhook-Delivery-ID":88360
            },
            "request_body":"redacted",
            "response_code":"201",
            "response_message":"Created",
            "response_headers":{
              "server":"nginx",
              "date":"Wed, 30 Jul 2014 19:17:46 GMT",
              "content-type":"text/plain",
              "content-length":"7",
              "connection":"close",
              "x-powered-by":"Express",
              "set-cookie":"connect.sid=4M9NNCZC88SriJ804h1v3Xdh.W1FVOFnHZk6e%2FT1xV6YiF1n1tSfwb7RlbZKW2V%2F49oc; path=/; expires=Wed, 30 Jul 2014 23:17:46 GMT; httpOnly",
              "x-response-time":"24ms"
            },
            "response_body":"Created",
            "created_at":"2014-07-30T19:17:47Z"
          }
        }
        ```

### Get a single webhook delivery [GET]

+ Response 200

    [Webhook Delivery][]

## Webhook Deliveries Collection [/webhooks/{webhook_id}/deliveries{?fields}]

A collection of webhook deliveries

+ Parameters
    * fields (optional, string, `duration,summary`) ... see Parameters > Fields Parameter documentation

+ Model (application/json)

    + Body

        ```json
        {
          "webhook_deliveries":[
            {
              "id":88414,
              "duration":"0.32718",
              "summary":"HTTP 201 Created: Created",
              "request_method":"POST",
              "request_url":"http://postcatcher.in/catchers/53d951274c003202000005b8",
              "request_headers":{
                "User-Agent":"WooCommerce/2.2.0 Hookshot (WordPress/3.9.2)",
                "Content-Type":"application/json",
                "X-WC-Webhook-Topic":"order.created",
                "X-WC-Webhook-Resource":"order",
                "X-WC-Webhook-Event":"created",
                "X-WC-Webhook-Signature":"0pxotPlyksQXYrVcFhYXraLbe3Rd1nT9YcKjIZdQZ1M=",
                "X-WC-Webhook-ID":21537,
                "X-WC-Webhook-Delivery-ID":88414
              },
              "request_body":"redacted",
              "response_code":"201",
              "response_message":"Created",
              "response_headers":{
                "server":"nginx",
                "date":"Sat, 30 Aug 2014 20:46:03 GMT",
                "content-type":"text/plain",
                "content-length":"7",
                "connection":"close",
                "x-powered-by":"Express",
                "set-cookie":"connect.sid=XaOYY0ObzvdebXJEtcoPxNaI.0%2FDAnUQ%2F5dK40wMV5uDtn1n9jli0ajQzuFoR7rQWPkw; path=/; expires=Sun, 31 Aug 2014 00:46:03 GMT; httpOnly",
                "x-response-time":"72ms"
              },
              "response_body":"Created",
              "created_at":"2014-08-30T20:46:03Z"
            },
            {
              "id":88412,
              "duration":"0.05861",
              "summary":"HTTP 201 Created: Created",
              "request_method":"POST",
              "request_url":"http://postcatcher.in/catchers/53d951274c003202000005b8",
              "request_headers":{
                "User-Agent":"WooCommerce/2.2.0 Hookshot (WordPress/3.9.2)",
                "Content-Type":"application/json",
                "X-WC-Webhook-Topic":"order.created",
                "X-WC-Webhook-Resource":"order",
                "X-WC-Webhook-Event":"created",
                "X-WC-Webhook-Signature":"vpvAozA4fYLEIwMxpKKPKwLKI+4+sbQeYMo/7zfbAzc=",
                "X-WC-Webhook-ID":21537,
                "X-WC-Webhook-Delivery-ID":88412
              },
              "request_body":"redacted",
              "response_code":"201",
              "response_message":"Created",
              "response_headers":{
                "server":"nginx",
                "date":"Sat, 30 Aug 2014 17:53:45 GMT",
                "content-type":"text/plain",
                "content-length":"7",
                "connection":"close",
                "x-powered-by":"Express",
                "set-cookie":"connect.sid=2sv50gykrbZFBGTn7fT3ueGy.%2BLaLBrx%2FEq0%2F%2F72HqwZnz0B%2BKUbyk9X0wfBiC1fjWYU; path=/; expires=Sat, 30 Aug 2014 21:53:45 GMT; httpOnly",
                "x-response-time":"11ms"
              },
              "response_body":"Created",
              "created_at":"2014-08-30T17:53:46Z"
            }
          ]
        }
        ```

### List all Webhook Deliveries [GET]

+ Response 200

    [Webhook Deliveries Collection][]

