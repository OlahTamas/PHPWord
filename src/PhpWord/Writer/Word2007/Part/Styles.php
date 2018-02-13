<?php
/**
 * This file is part of PHPWord - A pure PHP library for reading and writing
 * word processing documents.
 *
 * PHPWord is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPWord/contributors.
 *
 * @see         https://github.com/PHPOffice/PHPWord
 * @copyright   2010-2017 PHPWord contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpWord\Writer\Word2007\Part;

use PhpOffice\Common\XMLWriter;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font as FontStyle;
use PhpOffice\PhpWord\Style\Paragraph as ParagraphStyle;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use PhpOffice\PhpWord\Writer\Word2007\Style\Font as FontStyleWriter;
use PhpOffice\PhpWord\Writer\Word2007\Style\Paragraph as ParagraphStyleWriter;
use PhpOffice\PhpWord\Writer\Word2007\Style\Table as TableStyleWriter;

/**
 * Word2007 styles part writer: word/styles.xml
 *
 * @todo Do something with the numbering style introduced in 0.10.0
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod) For writeFontStyle, writeParagraphStyle, and writeTableStyle
 */
class Styles extends AbstractPart
{
    /**
     * Write part
     *
     * @return string
     */
    public function write()
    {
        $xmlWriter = $this->getXmlWriter();

        $xmlWriter->startDocument('1.0', 'UTF-8', 'yes');
        $xmlWriter->startElement('w:styles');
        $xmlWriter->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xmlWriter->writeAttribute('xmlns:w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Write default styles
        $styles = Style::getStyles();
        $this->writeDefaultStyles($xmlWriter, $styles);
        //$this->writeLatentStyles($xmlWriter, $styles);

        // Write styles
        if (count($styles) > 0) {
            foreach ($styles as $styleName => $style) {
                if ($styleName == 'Normal') {
                    continue;
                }

                // Get style class and execute if the private method exists
                $styleClass = substr(get_class($style), strrpos(get_class($style), '\\') + 1);
                $method = "write{$styleClass}Style";
                if (method_exists($this, $method)) {
                    $this->$method($xmlWriter, $styleName, $style);
                }
            }
        }

        $xmlWriter->endElement(); // w:styles

        return $xmlWriter->getData();
    }

    /**
     * Write default font and other default styles.
     *
     * @param \PhpOffice\Common\XMLWriter $xmlWriter
     * @param \PhpOffice\PhpWord\Style\AbstractStyle[] $styles
     */
    private function writeDefaultStyles(XMLWriter $xmlWriter, $styles)
    {
        $fontName = PhpWordSettings::getDefaultFontName();
        $fontSize = PhpWordSettings::getDefaultFontSize();
        $language = $this->getParentWriter()->getPhpWord()->getSettings()->getThemeFontLang();
        $latinLanguage = ($language == null || $language->getLatin() === null) ? 'en-US' : $language->getLatin();

        // Default font
        $xmlWriter->startElement('w:docDefaults');
        $xmlWriter->startElement('w:rPrDefault');
        $xmlWriter->startElement('w:rPr');
        $xmlWriter->startElement('w:rFonts');
        $xmlWriter->writeAttribute('w:ascii', $fontName);
        $xmlWriter->writeAttribute('w:hAnsi', $fontName);
        $xmlWriter->writeAttribute('w:eastAsia', $fontName);
        $xmlWriter->writeAttribute('w:cs', $fontName);
        $xmlWriter->endElement(); // w:rFonts
        $xmlWriter->startElement('w:sz');
        $xmlWriter->writeAttribute('w:val', $fontSize * 2);
        $xmlWriter->endElement(); // w:sz
        $xmlWriter->startElement('w:szCs');
        $xmlWriter->writeAttribute('w:val', $fontSize * 2);
        $xmlWriter->endElement(); // w:szCs
        $xmlWriter->startElement('w:lang');
        $xmlWriter->writeAttribute('w:val', $latinLanguage);
        if ($language != null) {
            $xmlWriter->writeAttributeIf($language->getEastAsia() !== null, 'w:eastAsia', $language->getEastAsia());
            $xmlWriter->writeAttributeIf($language->getBidirectional() !== null, 'w:bidi', $language->getBidirectional());
        }
        $xmlWriter->endElement(); // w:lang
        $xmlWriter->endElement(); // w:rPr
        $xmlWriter->endElement(); // w:rPrDefault
        $xmlWriter->endElement(); // w:docDefaults

        // Normal style
        $xmlWriter->startElement('w:style');
        $xmlWriter->writeAttribute('w:type', 'paragraph');
        $xmlWriter->writeAttribute('w:default', '1');
        $xmlWriter->writeAttribute('w:styleId', 'Normal');
        $xmlWriter->startElement('w:name');
        $xmlWriter->writeAttribute('w:val', 'Normal');
        $xmlWriter->endElement(); // w:name
        if (isset($styles['Normal'])) {
            $styleWriter = new ParagraphStyleWriter($xmlWriter, $styles['Normal']);
            $styleWriter->write();
        }
        $xmlWriter->endElement(); // w:style

        // FootnoteReference style
        if (!isset($styles['FootnoteReference'])) {
            $xmlWriter->startElement('w:style');
            $xmlWriter->writeAttribute('w:type', 'character');
            $xmlWriter->writeAttribute('w:styleId', 'FootnoteReference');
            $xmlWriter->startElement('w:name');
            $xmlWriter->writeAttribute('w:val', 'Footnote Reference');
            $xmlWriter->endElement(); // w:name
            $xmlWriter->writeElement('w:semiHidden');
            $xmlWriter->writeElement('w:unhideWhenUsed');
            $xmlWriter->startElement('w:rPr');
            $xmlWriter->startElement('w:vertAlign');
            $xmlWriter->writeAttribute('w:val', 'superscript');
            $xmlWriter->endElement(); // w:vertAlign
            $xmlWriter->endElement(); // w:rPr
            $xmlWriter->endElement(); // w:style
        }
    }

    /**
     * Write font style.
     *
     * @param \PhpOffice\Common\XMLWriter $xmlWriter
     * @param string $styleName
     * @param \PhpOffice\PhpWord\Style\Font $style
     */
    private function writeFontStyle(XMLWriter $xmlWriter, $styleName, FontStyle $style)
    {
        $paragraphStyle = $style->getParagraph();
        $styleType = $style->getStyleType();
        $type = ($styleType == 'title') ? 'paragraph' : 'character';
        if (!is_null($paragraphStyle)) {
            $type = 'paragraph';
        }

        $xmlWriter->startElement('w:style');
        $xmlWriter->writeAttribute('w:type', $type);

        // Heading style
        if ($styleType == 'title') {
            $arrStyle = explode('_', $styleName);
            $styleId = 'Heading' . $arrStyle[1];
            $styleName = 'heading ' . $arrStyle[1];
            $styleLink = 'Heading' . $arrStyle[1] . 'Char';
            $xmlWriter->writeAttribute('w:styleId', $styleId);

            $xmlWriter->startElement('w:link');
            $xmlWriter->writeAttribute('w:val', $styleLink);
            $xmlWriter->endElement();
        } elseif (!is_null($paragraphStyle)) {
            // if type is 'paragraph' it should have a styleId
            $xmlWriter->writeAttribute('w:styleId', $styleName);
        }

        // Style name
        $xmlWriter->startElement('w:name');
        $xmlWriter->writeAttribute('w:val', $styleName);
        $xmlWriter->endElement();

        // Parent style
        if (!is_null($paragraphStyle)) {
            if ($paragraphStyle->getStyleName() != null) {
                $xmlWriter->writeElementBlock('w:basedOn', 'w:val', $paragraphStyle->getStyleName());
            } elseif ($paragraphStyle->getBasedOn() != null) {
                $xmlWriter->writeElementBlock('w:basedOn', 'w:val', $paragraphStyle->getBasedOn());
            }
        }

        // w:pPr
        if (!is_null($paragraphStyle)) {
            $styleWriter = new ParagraphStyleWriter($xmlWriter, $paragraphStyle);
            $styleWriter->write();
        }

        // w:rPr
        $styleWriter = new FontStyleWriter($xmlWriter, $style);
        $styleWriter->write();

        $xmlWriter->endElement();
    }

    /**
     * Write paragraph style.
     *
     * @param \PhpOffice\Common\XMLWriter $xmlWriter
     * @param string $styleName
     * @param \PhpOffice\PhpWord\Style\Paragraph $style
     */
    private function writeParagraphStyle(XMLWriter $xmlWriter, $styleName, ParagraphStyle $style)
    {
        $xmlWriter->startElement('w:style');
        $xmlWriter->writeAttribute('w:type', 'paragraph');
        $xmlWriter->writeAttribute('w:customStyle', '0');
        $xmlWriter->writeAttribute('w:styleId', $styleName);
        $xmlWriter->startElement('w:name');
        $xmlWriter->writeAttribute('w:val', $styleName);
        $xmlWriter->endElement();

        // adding custom font style link if exists
/*          if (array_key_exists('font_'.$styleName, Style::getStyles())) {
            $xmlWriter->startElement('w:link');
            $xmlWriter->writeAttribute('w:val', 'font_'.$styleName);
            $xmlWriter->endElement();
        }
 */
        // Parent style
        $basedOn = $style->getBasedOn();
        $xmlWriter->writeElementIf(!is_null($basedOn), 'w:basedOn', 'w:val', $basedOn);

        // Next paragraph style
        $next = $style->getNext();
        $xmlWriter->writeElementIf(!is_null($next), 'w:next', 'w:val', $next);

        // w:pPr
        $styleWriter = new ParagraphStyleWriter($xmlWriter, $style);
        $styleWriter->write();

        // w:rPr, if font style linked
        if (array_key_exists('font_'.$styleName, Style::getStyles())) {
            $fontStyle = Style::getStyles()['font_'.$styleName];
            $xmlWriter->startElement('w:rPr');
            if ($fontStyle->isBold()) {
                $xmlWriter->writeElement('w:b');
            }
            if ($fontStyle->isItalic()) {
                $xmlWriter->writeElement('w:i');
            }
            $xmlWriter->startElement('w:sz');
            $xmlWriter->writeAttribute('w:val', $fontStyle->getSize());
            $xmlWriter->endElement();
            $xmlWriter->endElement();
        }
        $styleWriter = new ParagraphStyleWriter($xmlWriter, $style);
        $styleWriter->write();

        $xmlWriter->endElement();
    }

    /**
     * Write table style.
     *
     * @param \PhpOffice\Common\XMLWriter $xmlWriter
     * @param string $styleName
     * @param \PhpOffice\PhpWord\Style\Table $style
     */
    private function writeTableStyle(XMLWriter $xmlWriter, $styleName, TableStyle $style)
    {
        $xmlWriter->startElement('w:style');
        $xmlWriter->writeAttribute('w:type', 'table');
        $xmlWriter->writeAttribute('w:customStyle', '1');
        $xmlWriter->writeAttribute('w:styleId', $styleName);
        $xmlWriter->startElement('w:name');
        $xmlWriter->writeAttribute('w:val', $styleName);
        $xmlWriter->endElement();
        $xmlWriter->startElement('w:uiPriority');
        $xmlWriter->writeAttribute('w:val', '99');
        $xmlWriter->endElement();

        $styleWriter = new TableStyleWriter($xmlWriter, $style);
        $styleWriter->write();

        $xmlWriter->endElement(); // w:style
    }

    private function writeLatentStyles(XMLWriter $xmlWriter, $styles)
    {
        $xmlWriter->startElement('w:latentStyles');
        $xmlWriter->writeAttribute('w:defLockedState', '0');
        $xmlWriter->writeAttribute('w:defUIPriority', '99');
        $xmlWriter->startElement('w:lsdException');
        $xmlWriter->writeAttribute('w:name', 'toc 1');
        $xmlWriter->writeAttribute('w:semiHidden', '1');
        $xmlWriter->writeAttribute('w:uiPriority', '9');
        $xmlWriter->writeAttribute('w:unhideWhenUsed', '1');
        $xmlWriter->endElement(); // w:lsdException
        $xmlWriter->startElement('w:lsdException');
        $xmlWriter->writeAttribute('w:name', 'TJ 1');
        $xmlWriter->writeAttribute('w:semiHidden', '1');
        $xmlWriter->writeAttribute('w:uiPriority', '9');
        $xmlWriter->writeAttribute('w:unhideWhenUsed', '1');
        $xmlWriter->endElement(); // w:lsdException
        $xmlWriter->endElement(); // w:latentStyles
    }

}
