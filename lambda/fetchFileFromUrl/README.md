# DocVault Lambda Functions

### fetchFileFromUrl.js

This is a Node.js file that fetches remote files from URLs, and imports them to an S3 bucket. It's intended that Lambda will trigger this script as the main DocVault-S3 go-between, though they should also be triggerable directly.
