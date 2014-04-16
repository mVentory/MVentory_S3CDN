This extension stores product images on Amazon S3. It greately reduces server reqirements and allows using Amazon CloudFront CDN.

All product photos uploaded to Magento `media` folder are copied to a preconfigured S3 bucket.
Magento generates resized product images on demand and stores them in `media` folder for re-use. This extension requires product image sizes to be known in advance to generate all possible sizes and store them on S3. It slightly increases the amount of stored data.


#####S3 structure

Images are stored in S3 with the same names and paths as in Magento. The root path includes the s3 domain name, the bucket name, the site name, followed by the normal magento path.

     E.g. http://d3jbsz0dxpzi11.cloudfront.net/t1/215x170/1/3/1335478295542.jpg

where t1 is the name of the site/storefront.

#####Security policy

Magento admin stores an AWS key for accessing S3. Limit the account rights to minimise potential damage should the key fall into wrong hands.

1. Use IAM to create a user with limited access
2. Restrict uploads to the IP of your Magento server


##Installation

* Install the extension (magento connect link)
* You should see mVentory group with S3CDN tab on System Configuration page.
* Log out of Magento admin and log back in if you don't see the tab or get an error after clicking on it.

##AWS S3 configuration

These instructions are only a guide and are not intended to be the best practice recommendation.

1) Create a buket.

2) Create a read-only bucket policy:

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

3) Create a folder in the bucket with the name of your site for consistency. You can use any name or keep the files in the root of the bucket if your Magento installation has only one website configured.

4) Create an IAM user with the following permissions:

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
5) Save the access keys of the IAM user.

##Magento configuration 

1. Go to System -> Configuration -> mVentory / CDN
2. Log out and log in if you get 404 error
3. Copy access keys and bucket name to the Default configuration level
4. Enter a comma-separated list of file sizes (Resizing dimentions). E.g. you can use this list for the default theme:
`1200x900, 670x502, 310x, 300x, 215x170, 210x, 200x, 170x, 135x, 125x, 120x, 113x, 100x, 90x, 75x, 75x75, 70x, 50x`
5. Switch to the website level.
6. Enter the bucket name, if any. Enter only the name of the bucket, e.g. shop1. Do not enter the rest of the path.
7. Save
8. Press on `Upload placeholders` button to generate standard magento image placeholders of the specified dimensions and upload them to S3.
9. Switch to General/Web tab and enter the URL of the bucket into `Base Media URL` text boxes in Secure and Unsecure section. We recommend using a CloudFront URL. 
Examples:
 	
`https://s3.amazonaws.com/amn34/` for direct S3 access
`http://dk721sbikbl1.cloudfront.net/` via CloudFront


####Testing the set up.

1. Rename the existing media folder and create a new one so that it is empty and you still have all your files locally.
2. Create a new product.
3. The URLs of the product images should be pointing at AWS.

 
##Image migration

Use ... to migrate all original images from the local storage to S3.

The script can called from ... and requires ... the user to be logged in as ... .

Resizing dimensions are taken from mage config.

Uploaded images are not deleted.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.
 
 
##Bulk image resizing

Use ... to resize original images on S3 to something else.

The script can called from ... and requires ... the user to be logged in as ... . It can be done from the same magento instance or from any other, as long as the keys, paths and sizes match.

Resizing dimensions are taken from mage config.

The extension downloads the originals from S3, resizes them and uploads the resized images to their location in S3.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.



##How it works

####Image uploading

A newly uploaded imaged is stored in the media folder at first. The controller uploads it to S3, resizes and uploads all resized images to S3 as well. The user get a response from the server after uploading to S3 is finished. Errors are written to the log, some information is returned to the user.

Locally stored images are left in media folder, but can be deleted any time to free space.



####Image deletion

Images deleted via ... are deleted from S3. If an image is deleted by some other means it will not be deleted from S3.


####Displaying images from S3

mVentory substitutes the normal magento path with the path from Admin/System/Config/Web/Base Media URL followed by the normal Magento path and the file name. There should be no need to change the theme, unless it bypasses normal magento path generation functions.

All image sizes must exist in S3. No dynamic reszing takes place.

Make sure that the placeholder image is uploaded to S3 as well.

All code for displaying images normally takes their URLs from "catalog/image" helper (app/code/core/Mage/Catalog/Helper/Image.php). We redefined this class with our own (app/code/community/MVentory/Tm/Helper/Image.php) and have overridden the toString() method so that it returns URLs pointing to images on CDN.
Compatibility with other extensions

Access to S3 is abstracted by redefining ... in mVentory. If a file is not found in the local storage mVentory tried to download it from S3 for other extensions to use. If an image is saved via ... it is uploaded to S3. Remember that /media/ folder can be purged at any time.
