Introduction

All product images are stored on S3. mVentory supports uploading new, displaying existing and moving them in bulk between the site, user and S3.

S3 holds all images, including all resized ones. No dynamic resizing takes place.
S3 structure

Images are stored in S3 with the same names and paths as in Mage. The root path includes the s3 domain name, the bucket name, the site name, followed by the normal magento path.

E.g. http://d3jbsz0dxpzi11.cloudfront.net/t1/215x170/1/3/1335478295542.jpg

where t1 is the name of the site/storefront.
Security policy

    Restrict uploads to the IP of the magento server.
    Use a key for a user specially created for that purpose, one per site
    Restrict the user rights to uploading image only, use IAM for this
    Downloading images is public

Configuration

Use Product Images CDN tab inside mVentory tab in mage system config.

Bucket name: just the name of the bucket, no paths, e.g. mventorytest

Resized dimensions: a comma-separate list, e.g. 300x, 300x300, 70x70, 170x170, 215x170, 65x65, 310x, 210x, 90x
Image uploading

A newly uploaded imaged is stored in the media folder at first. The controller uploads it to S3, resizes and uploads all resized images to S3 as well. The user get a response from the server after uploading to S3 is finished. Errors are written to the log, some information is returned to the user.

Locally stored images are left in media folder, but can be deleted any time to free space.
Image migration

Use ... to migrate all original images from the local storage to S3.

The script can called from ... and requires ... the user to be logged in as ... .

Resizing dimensions are taken from mage config.

Uploaded images are not deleted.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.
Image deletion

Images deleted via ... are deleted from S3. If an image is deleted by some other means it will not be deleted from S3.
Bulk image resizing

Use ... to resize original images on S3 to something else.

The script can called from ... and requires ... the user to be logged in as ... . It can be done from the same magento instance or from any other, as long as the keys, paths and sizes match.

Resizing dimensions are taken from mage config.

The extension downloads the originals from S3, resizes them and uploads the resized images to their location in S3.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.
Displaying images from S3

mVentory substitutes the normal magento path with the path from Admin/System/Config/Web/Base Media URL followed by the normal Magento path and the file name. There should be no need to change the theme, unless it bypasses normal magento path generation functions.

All image sizes must exist in S3. No dynamic reszing takes place.

Make sure that the placeholder image is uploaded to S3 as well.

All code for displaying images normally takes their URLs from "catalog/image" helper (app/code/core/Mage/Catalog/Helper/Image.php). We redefined this class with our own (app/code/community/MVentory/Tm/Helper/Image.php) and have overridden the toString() method so that it returns URLs pointing to images on CDN.
Compatibility with other extensions

Access to S3 is abstracted by redefining ... in mVentory. If a file is not found in the local storage mVentory tried to download it from S3 for other extensions to use. If an image is saved via ... it is uploaded to S3. Remember that /media/ folder can be purged at any time.