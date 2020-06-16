# PageImage Manipulator beta for ProcessWire 2.3+ and Pageimage Manipulator 2 for PW 2.5.11+

This module provide basic Imagemanipulations for PageImages and Imagefiles


The Page Image Manipulator is a module that let you in a first place do ImageManipulations with your PageImages. - And in a second place there is the possibility to let it work on any imagefile that exists in your servers filesystem, regardless if it is a 'known PW-image'.

The Page Image Manipulator is a Toolbox for Users and Moduledevelopers. It is written to be as close to the Core ImageSizer as possible. Besides the GD-filterfunctions it contains resize, crop, rotate, flip, sharpen and 3 watermark methods.



### How does it work?

You can enter the ImageManipulator by calling the method pimLoad(). After that you can chain together how many actions in what ever order you like. If your manipulation is finished, you call pimSave() to write the memory Image into a diskfile. pimSave() returns the PageImage-Object of the new written file so we are able to further use any known PW-image property or method. This way it integrates best into the ProcessWire flow.

You have to define a name-prefix that you pass with the pimLoad() method. If the file with that prefix already exists, all operations are skipped and only the desired PageImage-Object gets returned by pimSave(). If you want to force recreation of the file, you can pass as second param a boolean true: pimLoad('myPrefix', true).

You may also want to get rid of all variations at once? Than you can call $pageimage->pimLoad('myPrefix')->removePimVariations()!

A complete list of all methods and actions can be found here: http://processwire.com/talk/topic/4264-release-page-image-manipulator/



### Version history


#### 0.2.10 / 0.2.5

+ minor fix with outputformat extensions jpg and jpeg


#### 0.2.9 / 0.2.4

+ corrected version numbers


#### 0.2.8

+ Pim2 added a switch to getPimVariations() to refresh the cached array, default = always refresh, passing a boolean false uses the cached version.
   Many thanks to CaelanStewart! (see: https://processwire.com/talk/topic/4264-page-image-manipulator-1/?page=9#comment-133357)


#### 0.2.7

+ Pim2 fixed a bug with naming scheme of Pageimage Variationnames, a missing dot that prevented PW from detecting the variations
+ Pim2 now uses wireChmod on save.


#### 0.2.0 / 0.2.6

+ released Pim2 to support PW 2.6+ with it's new naming scheme. With new sites under PW 2.6+ please
   use directly ->pim2Load() instead of the old ->pimLoad(). If you need to upgrade on existing sites
   with already lots of images, please refer to this forum post:
   https://processwire.com/talk/topic/9982-page-image-manipulator-2/


#### 0.1.5

+ fixed a bug found by @rot: the modue was not set to singular in the module header,
   https://processwire.com/talk/topic/4264-release-page-image-manipulator/page-8#entry92006

#### 0.1.4

+ fixed a bug regarding permission "image-crop" when working together with Thumbnails/CropImage module found by @Pete
  (this needs further investigation, here it is a quick solution to stop the error
   https://processwire.com/talk/topic/4264-release-page-image-manipulator/page-6#entry81356)

#### 0.1.3

+ fixed a bug with ignoring outputFormat when send as $options with method pimLoad found by @titanium
+ added support for php versions with buggy GD-lib for sharpening and unsharpMask
+ added support for the coming module PageimageNamingScheme into pimVariations()

#### 0.1.2

+ added support for sharpening-value 'none'

#### 0.1.1

+ added a hook that add pim_* variations to pageimage-variation-collection

#### 0.1.0

+ added method getPimVariations()

#### 0.0.9

+ fixed issue with pimSave, added a check if DIB was loaded, and if not do it.

#### 0.0.8

+ added enhanced support for Thumbnails module, including permanent storage for CropRectangleCoords and params
+ fixed / rewritten all bg-color stuff to support rgba alpha channel
+ fixed a E-Notice with IPTC prepare
+ changed the params of method resize, width, height to be the same like in new ImageSizer, ($sharpen can have value 'none') Sorry for breaking compatibility!

#### 0.0.5

+ added method canvas
+ added method unsharpMask

#### 0.0.4

+ added method watermarkText

#### 0.0.3

+ added support for positioning the watermark in method watermarkLogo
