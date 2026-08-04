:navigation-title: FAQ

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  _faq-installation:

How can I install this extension?
==================================

See chapter :ref:`installation`.

..  _faq-cache-button:

How do I activate the CELUM cache-clear button?
================================================

Add ``options.clearCache.celum = 1`` to the TypoScript configuration
under the user or group settings. See chapter :ref:`usage`.

..  _license-key:

How do I get my license key?
=============================

The license key is issued by brix IT solutions for one specific CELUM
instance. It is not generated in CELUM itself — see :ref:`help` to request one.

The key encodes both the base URL of the CELUM REST API and an expiry date, so
it pins the instance the driver talks to. A separate host setting is therefore
neither needed nor available.

..  note::

    If the URL contained in the license key has no path, the driver appends
    ``/content-api/v1`` automatically.

..  tip::

    If the license key is invalid or expired, the storage stays empty instead of
    raising an error in the backend. Check the TYPO3 log for
    ``No valid license`` to confirm.

..  _root-node-ids:

How do I find my Root Node IDs?
================================

The root node ID is the numerical ID of the CELUM folder that will serve as the entry point in the TYPO3 file selection:

1. In CELUM Content, navigate to the folder you want to use as the root — the ID is visible in the URL (e.g. ``1234_node``)
2. Open the folder's **Detail View** — the ID is visible in the upper left corner.

Multiple root node IDs can be entered as a comma-separated list — all configured nodes and their sub-nodes will be available recursively in TYPO3.

..  _faq-help:

Where to get help?
==================

See chapter :ref:`help`.
