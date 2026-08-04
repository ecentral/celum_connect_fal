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
    See :ref:`license-key` for instructions on how to obtain it.

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
