# Added to vcl_recv: what to do with a request.
#
# Runs before the platform's own handling of the request and before the
# framework's cookie preset (Drupal, WordPress), so a rule here sees the
# request as it arrived. `return (pass)` is yours to use; a `return (hash)`
# would skip that handling, cookie rules included — leave lookups to it.
#
# Not allowed in any of these files: a backend, vcl_init, import, include.
# At most 8 KB. Takes effect with the deploy that carries it.
#
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_recv {
    # Never from the cache, whatever the application sends: a cart, a
    # checkout, an account page.
    if (req.url ~ "^/(cart|checkout|my-account)(/|\?|$)") {
        return (pass);
    }

    # An editor's preview, asked for with a header by a headless front end.
    if (req.http.X-Preview) {
        return (pass);
    }

    # The visitor's currency, out of its cookie and into a header that
    # hash.vcl adds to the cache key. Taken here, before the framework preset
    # strips cookies it does not know, which would leave hash.vcl nothing to
    # read.
    unset req.http.X-Currency;
    if (req.http.Cookie ~ "(^|;\s*)currency=[A-Z]{3}(;|$)") {
        set req.http.X-Currency = regsub(req.http.Cookie, "^(.*;\s*)?currency=([A-Z]{3}).*$", "\2");
    }
}
