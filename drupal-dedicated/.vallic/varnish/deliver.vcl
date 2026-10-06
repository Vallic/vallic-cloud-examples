# Added to vcl_deliver, before the platform's own delivery. No `return` here.
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_deliver {
    # Drupal's own debugging headers, useful behind the cache and nowhere else.
    unset resp.http.X-Generator;
    unset resp.http.X-Drupal-Cache;
    unset resp.http.X-Drupal-Dynamic-Cache;
}
