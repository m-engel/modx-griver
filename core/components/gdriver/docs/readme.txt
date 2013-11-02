--------------------
GDriver 0.4.0
--------------------

First Released: not yet
Author: Michael Engel <me@mailfetch.de>
License: GNU GPLv2 (or later at your option)

This component adds an MediaResourceType for your GoogleDrive. Easy browsing your Files, download & upload Files. Other File Functions like move, create Directory and others will come in some time. The installation is sill rough, I hope i will be able to smooth it to make it easier to understand.

You need some configuration work with your Google Account maybe.
1. Its needed to go to https://code.google.com/apis/console/
2. create an Project if there is no one. 
3. Then activate on *Servies* the "Drive API"
4. On *API Access* you should create an client ID for an web application
5. Set the Redirect URI to www.yoursite.com/assets/components/gdriver/auth.php
6. Fill the ClientID, Client secret and your API Key into the System Settings of GDriver
7. Create an MediaResource for the GDriver, and try to Browse, you won`t see any files of directories!
8. Back on the System Settings an Description to the access_token appeared with an correct token-request link
9. finish

TODO:
- Lots of Functions
- Installation Tuning
- Reduce the GoogleSDK Framework to the needed files

Thanks for using GDriver
Michael Engel
me@mailfetch.de