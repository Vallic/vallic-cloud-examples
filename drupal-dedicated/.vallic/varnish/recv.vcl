# Added to vcl_recv, before the platform's handling and the Drupal preset.
# More rules, and what each file may hold: ../varnish/ and
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_recv {
    # A cart and a checkout are somebody's own, whatever Drupal sends.
    if (req.url ~ "^/(cart|checkout)(/|\?|$)") {
        return (pass);
    }
}
