# PageImage Manipulator beta for ProcessWire2.3+

This module provide basic Imagemanipulations for PageImages and Imagefiles


The Page Image Manipulator is a module that let you in a first place do ImageManipulations with your PageImages. - And in a second place there is the possibility to let it work on any imagefile that exists in your servers filesystem, regardless if it is a 'known PW-image'.

The Page Image Manipulator is a Toolbox for Users and Moduledevelopers. It is written to be as close to the Core ImageSizer as possible. Besides the GD-filterfunctions it contains resize, crop, rotate, flip, sharpen and 3 watermark methods.



### How does it work?

You can enter the ImageManipulator by calling the method pimLoad(). After that you can chain together how many actions in what ever order you like. If your manipulation is finished, you call pimSave() to write the memory Image into a diskfile. pimSave() returns the PageImage-Object of the new written file so we are able to further use any known PW-image property or method. This way it integrates best into the ProcessWire flow.

You have to define a name-prefix that you pass with the pimLoad() method. If the file with that prefix already exists, all operations are skipped and only the desired PageImage-Object gets returned by pimSave(). If you want to force recreation of the file, you can pass as second param a boolean true: pimLoad('myPrefix', true).

You may also want to get rid of all variations at once? Than you can call $pageimage->pimLoad('myPrefix')->removePimVariations()!

A complete list of all methods and actions can be found here: http://processwire.com/talk/topic/4264-release-page-image-manipulator/



### Version history

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
