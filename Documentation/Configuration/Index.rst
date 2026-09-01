..  _configuration:

=============
Configuration
=============

The driver is configured per FAL storage in the TYPO3 backend. Go to
:guilabel:`List` (page PID=0), create or edit a :guilabel:`File Storage` record,
set :guilabel:`Driver` to :guilabel:`BrixCelumDriver`, and fill in the
FlexForm fields described below.

..  confval-menu::
    :name: driver-config

..  confval:: licenseKey
    :name: licenseKey
    :type: string
    :required: true

    License key issued for your CELUM instance. It also determines the base URL
    of the CELUM REST API, so the instance the driver talks to is pinned by the
    license.

..  confval:: celumApiKey
    :name: celumApiKey
    :type: string
    :required: true

    API key of the authentication profile the driver uses. It is sent verbatim
    in the ``X-API-KEY`` header.
    See :ref:`api-key` for instructions on how to obtain it.

..  confval:: roots
    :name: roots
    :type: string
    :required: true

    Comma-separated list of CELUM collection IDs to expose as root folders,
    e.g. ``12,34,56``.
    See :ref:`root-node-ids` for instructions on how to find the IDs.

..  confval:: locale
    :name: locale
    :type: string (en | de)
    :default: en

    Language used for asset names returned by the API.

..  confval:: defaultLocale
    :name: defaultLocale
    :type: string (en | de)
    :default: en

    Fallback language when the primary locale is unavailable.

..  confval:: cacheLifetimeInMinutes
    :name: cacheLifetimeInMinutes
    :type: integer (1–29)
    :default: 29

    How long API responses are cached in the TYPO3 cache framework.

..  _configuration-check:

Configuration check
===================

Saving a file storage that uses the :guilabel:`BrixCelumDriver` runs a check
and reports the result as a flash message. The record is always saved, also
when the check fails - an API key may well be entered before it is activated
on the CELUM side.

The check does two things:

#.  It decodes :confval:`licenseKey` and examines its expiry date. This happens
    locally, without contacting CELUM.
#.  It sends one request to CELUM to find out whether :confval:`celumApiKey` is
    accepted. The request gives up after ten seconds so that saving a record
    never hangs on an unreachable service.

..  list-table::
    :header-rows: 1

    *   -   Message
        -   Type
        -   Meaning
    *   -   Connection to … succeeded
        -   Ok
        -   License and API key work, the storage is ready to use.
    *   -   No license key configured
        -   Error
        -   :confval:`licenseKey` is empty.
    *   -   The license key cannot be read
        -   Error
        -   The key is not valid base64, usually a typo or a truncated value.
    *   -   The license key does not contain a CELUM host and an expiry date
        -   Error
        -   The key decodes, but carries a different payload - it may belong to
            another product.
    *   -   The license key expired on …
        -   Error
        -   Request a new key from CELUM. The storage stays empty until then.
    *   -   The license key expires on …
        -   Warning
        -   The key runs out within the next 30 days.
    *   -   No root collections configured
        -   Warning
        -   :confval:`roots` is empty, so the storage has nothing to show.
    *   -   CELUM rejected the API key
        -   Error
        -   CELUM answered ``401`` or ``403``. Check :confval:`celumApiKey`
            against the authentication profile in the CMA.
    *   -   CELUM could not be reached
        -   Warning
        -   The request failed or timed out, so the API key could not be
            verified. The configuration may still be correct.

..  note::

    The check runs when the storage record is saved. A license that expires
    later is not noticed until the record is saved again.
