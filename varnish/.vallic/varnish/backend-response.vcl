# Added to vcl_backend_response: what to do with an answer from the site.
#
# Runs before the platform's own handling of the answer, which ends the
# subroutine.
#
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_backend_response {
    # A feed rebuilt hourly, kept an hour whatever the application says, and
    # without a cookie that would make it uncacheable.
    if (bereq.url ~ "^/feeds/") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 1h;
    }

    # An error is kept seconds, not minutes: the next visitor may get a
    # working page.
    if (beresp.status >= 500) {
        set beresp.ttl = 5s;
    }
}
