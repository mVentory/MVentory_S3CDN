#S3CDN Extension

The mVentory S3CDN extension moves product image storage from Magento server to Amazon S3. It greately reduces server requirements and allows using Amazon CloudFront CDN.

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

***

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

3) **Create a folder in the bucket.** You an use any name, but we recommend using the name of your website for consistency, e.g. _shop1_.

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

Determine the bucket URL. It can be in a form of `http://s3.amazonaws.com/<bucket_name>/some/path/to/file.jpg` (works only for some buckets in some regions) or `http://<bucket_name>.s3.amazonaws.com/some/path/to/file.jpg` (should work for all types of buckets from all regions),  where `<bucket_name>` should be replaced with your bucket name.
test the bucket URL.

You can verify the URL by manually uploading an image through the AWS console and making sure you can access it through the URL.

##Magento configuration 

1. Go to _System -> Configuration -> mVentory / CDN_.

2. Log out of admin and log back in in if you get 404 error (a known Magento quirk).

3. Enter Access and Secret Keys (that you saved in step 5 of AWS S3 configuration) and bucket name to the _Default_ configuration level.

4. Enter a comma-separated list of image dimensions (in pixels). This list must include all dimensions used in your theme. E.g. you can use this list for the default theme:
`50x, 56x, 75x75, 75x, 76x, 85x, 100x100, 113x113, 125x125, 125x, 135x, 265x`.

5. Save config and switch to the website level.

6. Enter the S3 folder name (from step 3 of AWS configuration). E.g. _shop1_.

7. Save config. *Do not proceed to uploading placeholders without saving.*

8. Press on `Upload placeholders to CDN` button to generate standard Magento image placeholders of the specified dimensions and upload them to S3.

9. Switch to _General/Web_ tab. Enter the bucket URL into _Base Media URL_ text boxes in _Secure_ and _Unsecure_ sections. We recommend using a CloudFront URL.
Examples:
 	
* `http://<bucket_name>.s3.amazonaws.com/` for direct S3 access
* `http://dk721sbikbl1.cloudfront.net/` via CloudFront

### Testing the set up

1. Create a new product and view it in the front-end.
2. The URLs of the product images should be pointing at AWS and images should be displayed correctly.

### Troubleshooting

1. Check the URL of the images - must point to S3 or CloudFront.
2. Try accessing the image on S3 - should be public
3. Use AWS console to check if the image exists on S3
4. Check if the bucket has a policy for public read-only access

***
 
##Image migration

Use `utils/upload-to-s3` script to migrate all original product images from the local storage to S3. All other images in /media/ folder are ignored.

####Prep

1. Place the script into Magento root
2. Check your `max_execution_time`. The script may take a while.
3. Modify the script if you want to limit the scope of the images to a particular website.
4. Prepare an S3 bucket (see instructions above).
5. Prepare resizing dimensions (see instructions above)

We recomment to configure and test the extension first. Do not run the script if you cannot successfully upload images to S3 via the admin or [Magento Android App](http://mventory.com). 

####Notes

* Resizing dimensions are taken from mage config.
* Original files are uploaded as is, other dimensions are produced on the fly and uploaded.
* Uploaded images are not deleted.
* Errors are written to s3.log file. 
* The script does not stop on errors. 
* Files existing on S3 are overwritten by the local copy.
* The script may take a very long time to execute

***

##Bulk image resizing

Use `utils/resize-on-s3` script to resize original images on S3 to something else.

The script can called from ... and requires ... the user to be logged in as ... . It can be done from the same magento instance or from any other, as long as the keys, paths and sizes match.

Resizing dimensions are taken from mage config.

The extension downloads the originals from S3, resizes them and uploads the resized images to their location in S3.

Errors are written to an error log. The script will not stop on errors. Files existing on S3 are overwritten by the local copy.

***

##Testing the migration

1. Rename the existing media folder and create a new one so that it is empty and you still have all your files locally.
2. Create a new product and view it in the front-end.
3. The URLs of the product images should be pointing at AWS.
