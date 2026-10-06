# Added to vcl_deliver: headers on the way out.
#
# The platform's own delivery runs after this — it sets `x-vc-cache` and takes
# internal headers off — so no `return` here. A file that returns is refused
# and the policy runs without it.
#
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_deliver {
    # What the application is built with is nobody else's business.
    unset resp.http.X-Powered-By;
    unset resp.http.X-Generator;

    # A default, where the application set none.
    if (!resp.http.Referrer-Policy) {
        set resp.http.Referrer-Policy = "strict-origin-when-cross-origin";
    }
}
