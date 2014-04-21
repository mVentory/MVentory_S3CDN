This extension stores product images on Amazon S3. It greately reduces server requirements and allows using Amazon CloudFront CDN.

All product photos uploaded to Magento `media` folder are copied to a preconfigured S3 bucket.
Magento generates resized product images on demand and stores them in `media` folder for re-use. This extension requires product image sizes to be known in advance to generate all possible sizes and store them on S3. It slightly increases the amount of stored data.


#####S3 structure

Images are stored in S3 with the same names and paths as in Magento. The root path includes the s3 domain name, the bucket name, the site name (prefix), followed by the normal magento path.

     E.g. http://d3jbsz0dxpzi11.cloudfront.net/t1/215x170/1/3/1335478295542.jpg

where t1 is the name (prefix) of the site/storefront.

#####Security policy

Magento admin stores an AWS key for accessing S3. Limit the account rights to minimise potential damage should the key fall into wrong hands.

1. Use IAM to create a user with limited access
2. Restrict uploads to the IP of your Magento server


##Installation

* Install the extension (magento connect link)
* You should see mVentory group with CDN tab on _System Configuration_ page.
* Log out of Magento admin and log back in if you don't see the tab or get an error after clicking on it.

##AWS S3 configuration

These instructions are only a guide and are not intended to be the best practice recommendation.

1) **Create a buket.**

2) **Create a read-only bucket policy:**

```
{
    "Version": "2008-10-17",
    "Id": "Policy1234567",
    "Statement": [
    	{
    		"Sid": "Stmt1234567",
    		"Effect": "Allow",
    		"Principal": {
    			"AWS": "*"
    		},
    		"Action": "s3:GetObject",
    		"Resource": "arn:aws:s3:::mybucketname/*"
    	}
    ]
}
```
Replace `mybucketname` with the name of your bucket. Keep `/*` to allow access to files and subfolders, not just the bucket.

3) **Create a folder in the bucket** with the name of your site for consistency. You can use any name or keep the files in the root of the bucket if your Magento installation has only one website configured.

4) **Create an IAM user** with the following permissions:

```
{
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "s3:Get*",
           "s3:List*",
           "s3:Put*"
         ],
         "Resource": "arn:aws:s3:::mybucketname/*"
       }
     ]
}
```
5) **Save the access keys** of the IAM user.

####Bucket URL

Obtain and test the bucket URL. It can be in a form of `https://s3.amazonaws.com/<bucket_name>/some/path/to/file.jpg` (works only for some buckets in some regions) or `https://<bucket_name>.s3.amazonaws.com/some/path/to/file.jpg` (should work for all types of buckets from all regions),  where `<bucket_name>` should be replaced with your bucket name.

Test a manually uploaded image to make sure the URL is correct.

##Magento configuration 

1. Go to _System -> Configuration -> mVentory / CDN_

2. Log out of admin and log back in in if you get 404 error

3. Copy access keys and bucket name to the _Default_ configuration level

4. Enter a comma-separated list of file sizes (_Resizing dimentions_). E.g. you can use this list for the default theme:
`50x, 56x, 75x75, 75x, 76x, 85x, 100x100, 113x113, 125x125, 125x, 135x, 265x`

5. Switch to the website level.

6. _Enter the website prefix (or is this folder name in the S3 bucket??), if any. Enter only the prefix, e.g. `shop1`. Do not enter the rest of the path._

7. Save config.

8. Press on _Upload placeholders_ button to generate standard magento image placeholders of the specified dimensions and upload them to S3.

9. Switch to _General/Web_ tab. Enter the URL of the bucket into _Base Media URL_ text boxes in _Secure_ and _Unsecure_ section. We recommend using a CloudFront URL. 
Examples:
 	
* `https://amn34.s3.amazonaws.com/` for direct S3 access
* `http://dk721sbikbl1.cloudfront.net/` via CloudFront

#####How to get bucket URL

You can get your bucket URL from AWS console, but it may require a different endpoint for the actual files. 

E.g. Your bucket URL is usually `bucketname.s3.amazonaws.com`

####Testing the set up.

1. Rename the existing media folder and create a new one so that it is empty and you still have all your files locally.
2. Create a new product.
3. The URLs of the product images should be pointing at AWS.

 
##Image migration

Use `utils/upload-to-s3` script to migrate all original images from the local storage to S3.

The script can called from ... and requires ... the user to be logged in as ... .

Resizing dimensions are taken from mage config.

Uploaded images are not deleted.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.
 
 
##Bulk image resizing

Use `utils/resize-on-s3` script to resize original images on S3 to something else.

The script can called from ... and requires ... the user to be logged in as ... . It can be done from the same magento instance or from any other, as long as the keys, paths and sizes match.

Resizing dimensions are taken from mage config.

The extension downloads the originals from S3, resizes them and uploads the resized images to their location in S3.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.



##How it works

####Image uploading

A newly uploaded imaged is stored in the media folder at first. The controller uploads it to S3, resizes and uploads all resized images to S3 as well. The user gets a response from the server after uploading to S3 is finished. Errors are written to the log, some information is returned to the user.

Locally stored images are left in media folder, but can be deleted any time to free space.


####Image deletion

No images are deleted from S3 in the current release.


####Displaying images from S3

Since you replace the normal magento path in _Admin/System/Config/Web/Base Media URL_ with the path to the bucket then the front end builds the URL using the S3 path followed by the normal Magento path and the file name. There should be no need to change the theme, unless it bypasses normal magento path generation functions.

All image sizes must exist in S3. No dynamic resizing takes place.

Make sure that the placeholder image is uploaded to S3 as well.

All code for displaying images normally takes their URLs from "catalog/image" helper (app/code/core/Mage/Catalog/Helper/Image.php). We redefined this class with our own (app/code/community/MVentory/CDN/Helper/Image.php) and have overridden the toString() method so that it returns URLs pointing to images on CDN.
Compatibility with other extensions

Access to S3 is abstracted by redefining ... in mVentory. If a file is not found in the local storage mVentory tried to download it from S3 for other extensions to use. If an image is saved via ... it is uploaded to S3. Remember that /media/ folder can be purged at any time.


## List of files with images in Default theme

S3CDN extension resizes images before uploading them to S3 and thus needs a predefined list of image sizes used in the theme. Every theme is different, but they usually follow the structure of the default theme. The following list contains names of files used in the default theme where images are displayed. 

	checkout/cart/render/default.phtml: 75x
	checkout/cart/render/simple.phtml: 75x
	checkout/cart/crosssell.phtml: 75x
	giftmessage/inline.phtml: 75x
	wishlist/sidebar.phtml: 50x
	wishlist/shared.phtml: 113x113
	wishlist/item/column/image.phtml: 113x113
	wishlist/email/items.phtml: 135x
	tag/customer/view.phtml: 100x100
	review/customer/view.phtml: 125x125
	review/view.phtml: 125x125
	catalog/product/view/media.phtml: 265x, 56x
	catalog/product/compare/list.phtml: 125x125
	catalog/product/list/upsell.phtml: 125x
	catalog/product/list/related.phtml: 50x
	catalog/product/widget/new/column/new_images_list.phtml: 76x
	catalog/product/widget/new/column/new_default_list.phtml: 50x
	catalog/product/widget/new/content/new_list.phtml: 85x
	catalog/product/widget/new/content/new_grid.phtml: 85x
	catalog/product/new.phtml: 135x
	catalog/product/list.phtml: 135x
	reports/home_product_viewed.phtml: 135x
	reports/widget/compared/column/compared_images_list.phtml: 76x
	reports/widget/compared/column/compared_default_list.phtml: 50x
	reports/widget/compared/content/compared_grid.phtml: 85x
	reports/widget/compared/content/compared_list.phtml: 85x
	reports/widget/viewed/column/viewed_images_list.phtml: 76x
	reports/widget/viewed/column/viewed_default_list.phtml: 50x
	reports/widget/viewed/content/viewed_list.phtml: 85x
	reports/widget/viewed/content/viewed_grid.phtml: 85x
	reports/home_product_compared.phtml: 135x
	bundle/catalog/product/list/partof.phtml: 125x
	email/productalert/stock.phtml: 75x75



