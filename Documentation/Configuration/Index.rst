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

..  confval:: celumHost
    :name: celumHost
    :type: string
    :required: true

    Base URL of the CELUM REST API,
    e.g. ``https://your-celum-instance.example.com``.

..  confval:: celumApiKey
    :name: celumApiKey
    :type: string
    :required: true

    API key for authentication with the CELUM REST API.

..  confval:: celumUser
    :name: celumUser
    :type: string
    :required: false

    Optional username for HTTP basic authentication.

..  confval:: celumPassword
    :name: celumPassword
    :type: string
    :required: false

    Optional password for HTTP basic authentication.

..  confval:: roots
    :name: roots
    :type: string
    :required: true

    Comma-separated list of CELUM collection IDs to expose as root folders,
    e.g. ``12,34,56``.

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

..  confval:: imageDownloadFormat
    :name: imageDownloadFormat
    :type: string
    :default: largeprvw

    CELUM format key used for image public URLs, e.g. ``largeprvw``,
    ``preview``, or ``thumbnail``.

..  confval:: cacheLifetimeInMinutes
    :name: cacheLifetimeInMinutes
    :type: integer (1–29)
    :default: 29

    How long API responses are cached in the TYPO3 cache framework.
