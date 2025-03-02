<?php
/**
 * Contains the InvoicePrinter class.
 *
 * @author      Farjad Tahir
 *
 * @see         http://www.splashpk.com
 *
 * @license     GPL
 *
 * @since       2017-12-15
 */

namespace Pyromn\PdfInvoice;

use tFPDF;

class InvoicePrinter extends tFPDF
{
    public const CHARSET_INPUT = 'UTF-8';

    public const INVOICE_SIZE_LEGAL = 'legal';
    public const INVOICE_SIZE_LETTER = 'letter';
    public const INVOICE_SIZE_A4 = 'a4';

    public const NUMBER_SEPARATOR_DOT = '.';
    public const NUMBER_SEPARATOR_COMMA = ',';
    public const NUMBER_SEPARATOR_SPACE = ' ';

    public const NUMBER_ALIGNMENT_LEFT = 'left';
    public const NUMBER_ALIGNMENT_RIGHT = 'right';

    public $angle = 0;
    public $font = 'DejaVu';                    /* Font Name : See inc/fpdf/font for all supported fonts */
    public $columnOpacity = 0.06;               /* Items table background color opacity. Range (0.00 - 1) */
    public $columnSpacing = 0.3;                /* Spacing between Item Tables */
    public $referenceformat = [                 /* Currency formater */
        'decimals_sep' => self::NUMBER_SEPARATOR_DOT,       /* Separator before decimals */
        'thousands_sep' => self::NUMBER_SEPARATOR_COMMA,    /* Separator between group of 3 numbers */
        'alignment' => self::NUMBER_ALIGNMENT_LEFT,         /* Price alignment in the column */
        'space' => false,                                   /* Space between currency and amount */
        'negativeParenthesis' => false                      /* Parenthesis arund price */
    ];
    public $margins = [
        'l' => 15,
        't' => 15,
        'r' => 15,
    ]; /* l: Left Side , t: Top Side , r: Right Side */
    public $fontSizeProductDescription = 7;                /* font size of product description */

    public $lang;
    public $document;
    public $type;
    public $reference;
    public $logo;
    public $color;
    public $badgeColor;
    public $date;
    public $time;
    public $due;
    public $dueFill;
    public $from;
    public $to;
    public $items;
    public $totals;
    public $totalsAlignment = 'vertical';
    public $badge;
    public $addText;
    public $footernote;
    public $dimensions;
    public $display_tofrom = true;
    public $customHeaders = [];
    public $currency;
    public $maxImageDimensions;
    public $language;
    public $firstColumnWidth;
    public $title;
    public $itemsDescription;
    public $voucherDescription;
    public $voucherDescription2;
    public $quantityField;
    public $priceField;
    public $totalField;
    public $discountField;
    public $vatField;
    public $vatPriceField;
    public $productsEnded;
    protected $displayToFromHeaders = true;
    protected $columns = 1;

    public function __construct($size = self::INVOICE_SIZE_A4, $currency = '$', $language = 'en')
    {
        $this->items = [];
        $this->totals = [];
        $this->addText = [];
        $this->currency = $currency;
        $this->maxImageDimensions = [230, 130];
        $this->dimensions         = [61.0, 34.0];
        $this->from               = [''];
        $this->to                 = [''];
        $this->setLanguage($language);
        $this->setDocumentSize($size);
        $this->setColor('#222222');
        $this->firstColumnWidth = $this->document['w'] - $this->margins['l'] - $this->margins['r'];

        parent::__construct('P', 'mm', [$this->document['w'], $this->document['h']]);

        $this->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
        $this->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);

        $this->AliasNbPages();
        $this->SetMargins($this->margins['l'], $this->margins['t'], $this->margins['r']);
    }

    private function setLanguage($language)
    {
        $this->language = $language;
        include dirname(__DIR__) . '/inc/languages/' . $language . '.inc';
        /** @var array $lang */
        $this->lang = $lang;
    }

    private function setDocumentSize($dsize)
    {
        switch ($dsize) {
            case self::INVOICE_SIZE_LETTER:
                $document['w'] = 215.9;
                $document['h'] = 279.4;
                break;
            case self::INVOICE_SIZE_LEGAL:
                $document['w'] = 215.9;
                $document['h'] = 355.6;
                break;
            case self::INVOICE_SIZE_A4:
            default:
                $document['w'] = 210;
                $document['h'] = 297;
                break;
        }

        $this->document = $document;
    }

    private function resizeToFit($image)
    {
        list($width, $height) = getimagesize($image);
        $newWidth = $this->maxImageDimensions[0] / $width;
        $newHeight = $this->maxImageDimensions[1] / $height;
        $scale = min($newWidth, $newHeight);

        return [
            round($this->pixelsToMM($scale * $width)),
            round($this->pixelsToMM($scale * $height)),
        ];
    }

    private function pixelsToMM($val)
    {
        $mm_inch = 25.4;
        $dpi = 96;

        return ($val * $mm_inch) / $dpi;
    }

    private function hex2rgb($hex)
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = [$r, $g, $b];

        return $rgb;
    }

    private function br2nl($string)
    {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
    }

    public function changeLanguageTerm($term, $new)
    {
        $this->lang[$term] = $new;
    }

    public function getLanguageTerms()
    {
        return array_keys($this->lang);
    }

    public function isValidTimezoneId($zone)
    {
        try {
            new \DateTimeZone($zone);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function setTimeZone($zone = '')
    {
        if (!empty($zone) and $this->isValidTimezoneId($zone) === true) {
            date_default_timezone_set($zone);
        }
    }

    public function setType($title)
    {
        $this->title = $title;
    }

    public function setColor($rgbcolor)
    {
        $this->color = $this->hex2rgb($rgbcolor);
    }

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function setTime($time)
    {
        $this->time = $time;
    }

    public function setDue($date)
    {
        $this->due = $date;
    }

    public function setDueFill($date)
    {
        $this->dueFill = $date;
    }

    public function setLogo($logo = 0, $maxWidth = 0, $maxHeight = 0)
    {
        if ($maxWidth and $maxHeight) {
            $this->maxImageDimensions = [$maxWidth, $maxHeight];
        }
        $this->logo = $logo;
        $this->dimensions = $this->resizeToFit($logo);
    }

    public function hide_tofrom()
    {
        $this->display_tofrom = false;
    }

    public function hideToFromHeaders()
    {
        $this->displayToFromHeaders = false;
    }

    public function setFrom($data)
    {
        $this->from = $data;
    }

    public function setTo($data)
    {
        $this->to = $data;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    public function setNumberFormat($decimals_sep = self::NUMBER_SEPARATOR_DOT, $thousands_sep = self::NUMBER_SEPARATOR_COMMA, $alignment = self::NUMBER_ALIGNMENT_LEFT, $space = true, $negativeParenthesis = false)
    {
        $this->referenceformat = [
            'decimals_sep' => $decimals_sep,
            'thousands_sep' => $thousands_sep,
            'alignment' => $alignment,
            'space' => $space,
            'negativeParenthesis' => $negativeParenthesis
        ];
    }

    public function setFontSizeProductDescription($data)
    {
        $this->fontSizeProductDescription = $data;
    }

    public function setTotalsAlignment($alignment)
    {
        $this->totalsAlignment = $alignment;
    }

    public function flipflop()
    {
        $this->flipflop = true;
    }

    public function price($price)
    {
        $decimalPoint = $this->referenceformat['decimals_sep'];
        $thousandSeparator = $this->referenceformat['thousands_sep'];
        $alignment = $this->referenceformat['alignment'] ?? self::NUMBER_ALIGNMENT_LEFT;
        $spaceBetweenCurrencyAndAmount = isset($this->referenceformat['space']) ? (bool) $this->referenceformat['space'] : true;
        $space = $spaceBetweenCurrencyAndAmount ? ' ' : '';
        $negativeParenthesis = isset($this->referenceformat['negativeParenthesis']) ? (bool) $this->referenceformat['negativeParenthesis'] : false;

        $number = number_format($price, 2, $decimalPoint, $thousandSeparator);
        if ($negativeParenthesis && $price < 0) {
            $number = substr($number, 1);
            if ($alignment === self::NUMBER_ALIGNMENT_RIGHT) {
                return '(' . $number . $space . $this->currency . ')';
            } else {
                return '(' . $this->currency . $space . $number . ')';
            }
        } else {
            if ($alignment === self::NUMBER_ALIGNMENT_RIGHT) {
                return $number . $space . $this->currency;
            } else {
                return $this->currency . $space . $number;
            }
        }
    }

    public function addCustomHeader($title, $content)
    {
        $this->customHeaders[] = [
            'title' => $title,
            'content' => $content,
        ];
    }

    public function addItem($item, $description, $quantity, $vat, $vatPrice, $price, $discount, $total)
    {
        $itemColumns = 1;

        $p['item'] = $item;
        $p['description'] = $this->br2nl($description);
        $p['quantity'] = $quantity;

        if ($quantity !== false) {
            $p['quantity'] = $quantity;
            $this->quantityField = true;

            $itemColumns++;
        }

        if ($vat !== false) {
            $p['vat'] = $vat;
            if (is_numeric($vat)) {
                $p['vat'] = $this->price($vat);
            }
            $this->vatField = true;

            $itemColumns++;
        }

        if ($vatPrice !== false) {
            $p['vatPrice'] = $vatPrice;
            if (is_numeric($vatPrice)) {
                $p['vatPrice'] = $this->price($vatPrice);
            }
            $this->vatPriceField = true;

            $itemColumns++;
        }

        if ($price !== false) {
            $p['price'] = $price;
            if (is_numeric($price)) {
                $p['price'] = $this->price($price);
            }
            $this->priceField = true;

            $itemColumns++;
        }

        if ($total !== false) {
            $p['total'] = $total;
            if (is_numeric($total)) {
                $p['total'] = $this->price($total);
            }
            $this->totalField = true;

            $itemColumns++;
        }

        if ($discount !== false) {
            $p['discount'] = $discount;
            if (is_numeric($discount)) {
                $p['discount'] = $this->price($discount);
            }
            $this->discountField = true;

            $itemColumns++;
        }

        if (count($this->items) == 0) {
            $this->columns = $itemColumns;
            $this->firstColumnWidth -= ($itemColumns - 1) * 20;
        } else {
            if ($itemColumns > $this->columns) {
                $this->firstColumnWidth -= ($itemColumns - $this->columns) * 20;
                $this->columns = $itemColumns;
            }
        }

        $this->items[] = $p;
    }

    public function addTotal($name, $value, $colored = false)
    {
        $t['name'] = $name;
        $t['value'] = $value;
        if (is_numeric($value)) {
            $t['value'] = $this->price($value);
        }
        $t['colored'] = $colored;
        $this->totals[] = $t;
    }

    public function addTitle($title)
    {
        $this->addText[] = ['title', $title];
    }

    public function addParagraph($paragraph)
    {
        $paragraph = $this->br2nl($paragraph);
        $this->addText[] = ['paragraph', $paragraph];
    }

    public function addBadge($badge, $color = false)
    {
        $this->badge = $badge;

        if ($color) {
            $this->badgeColor = $this->hex2rgb($color);
        } else {
            $this->badgeColor = $this->color;
        }
    }

    public function setFooternote($note)
    {
        $this->footernote = $note;
    }

    public function render($name = '', $destination = '')
    {
        $this->AddPage();
        $this->Body();
        $this->AliasNbPages();

        return $this->Output($destination, $name);
    }

    public function Header()
    {
        if (isset($this->logo) and !empty($this->logo)) {
            $this->Image(
                $this->logo,
                $this->margins['l'],
                $this->margins['t'],
                $this->dimensions[0],
                $this->dimensions[1]
            );
        }

        //Title
        $this->SetTextColor(0, 0, 0);
        $this->SetFont($this->font, 'B', 12);
        if (isset($this->title) and !empty($this->title)) {
            $this->Cell(0, 5, mb_strtoupper($this->title, self::CHARSET_INPUT), 0, 1, 'R');
        }
        $this->SetFont($this->font, '', 9);
        $this->Ln(5);

        $lineheight = 5;
        //Calculate position of strings
        $this->SetFont($this->font, 'B', 9);
        $positionX = $this->document['w'] - $this->margins['l'] - $this->margins['r']
                     - max(
                         $this->GetStringWidth(mb_strtoupper($this->lang['number'], self::CHARSET_INPUT)),
                         $this->GetStringWidth(mb_strtoupper($this->lang['date'], self::CHARSET_INPUT)),
                         $this->GetStringWidth(mb_strtoupper($this->lang['due'], self::CHARSET_INPUT))
                     )
                     - max(
                         $this->GetStringWidth(mb_strtoupper((string)$this->reference, self::CHARSET_INPUT)),
                         $this->GetStringWidth(mb_strtoupper((string)$this->date, self::CHARSET_INPUT))
                     ) - 25;

        //Number
        if (!empty($this->reference)) {
            $this->Cell($positionX, $lineheight);
            //$this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Cell(
                32,
                $lineheight,
                mb_strtoupper($this->lang['number'], self::CHARSET_INPUT) . ':',
                0,
                0,
                'L'
            );
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, '', 9);
            $this->Cell(0, $lineheight, $this->reference, 0, 1, 'R');
        }
        //Date
        $this->Cell($positionX, $lineheight);
        $this->SetFont($this->font, '', 9);
        //$this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
        $this->Cell(32, $lineheight, mb_strtoupper($this->lang['date'], self::CHARSET_INPUT) . ':', 0, 0, 'L');
        $this->SetTextColor(50, 50, 50);
        $this->SetFont($this->font, '', 9);
        $this->Cell(0, $lineheight, $this->date, 0, 1, 'R');

        //Time
        if (!empty($this->time)) {
            $this->Cell($positionX, $lineheight);
            $this->SetFont($this->font, '', 9);
            //$this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Cell(
                32,
                $lineheight,
                mb_strtoupper($this->lang['time'], self::CHARSET_INPUT) . ':',
                0,
                0,
                'L'
            );
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, '', 9);
            $this->Cell(0, $lineheight, $this->time, 0, 1, 'R');
        }
        //Due date
        if (!empty($this->due)) {
            $this->Cell($positionX, $lineheight);
            $this->SetFont($this->font, '', 9);
            //$this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Cell(32, $lineheight, mb_strtoupper($this->lang['due'], self::CHARSET_INPUT) . ':', 0, 0, 'L');
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, '', 9);
            $this->Cell(0, $lineheight, $this->due, 0, 1, 'R');
        }
        //Due date fill
        if (!empty($this->dueFill)) {
            $this->Cell($positionX, $lineheight);
            $this->SetFont($this->font, '', 9);
            //$this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Cell(32, $lineheight, mb_strtoupper($this->lang['due_fill'], self::CHARSET_INPUT) . ':', 0, 0, 'L');
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, '', 9);
            $this->Cell(0, $lineheight, $this->dueFill, 0, 1, 'R');
        }
        //Custom Headers
        if (count($this->customHeaders) > 0) {
            foreach ($this->customHeaders as $customHeader) {
                $this->Cell($positionX, $lineheight);
                $this->SetFont($this->font, 'B', 9);
                $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
                $this->Cell(32, $lineheight, mb_strtoupper($customHeader['title'], self::CHARSET_INPUT) . ':', 0, 0, 'L');
                $this->SetTextColor(50, 50, 50);
                $this->SetFont($this->font, '', 9);
                $this->Cell(0, $lineheight, $customHeader['content'], 0, 1, 'R');
            }
        }

        //First page
        if ($this->PageNo() == 1) {
            $dimensions = $this->dimensions[1] ?? 0;
            if (($this->margins['t'] + $dimensions) > $this->GetY()) {
                $this->SetY($this->margins['t'] + $dimensions + 5);
            } else {
                $this->SetY($this->GetY() + 2);
            }
            $this->Ln(5);
            $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
            $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);

            $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
            $this->SetFont($this->font, 'B', 10);
            $width = ($this->document['w'] - $this->margins['l'] - $this->margins['r']) / 2;
            if (isset($this->flipflop)) {
                $to = $this->lang['to'];
                $from = $this->lang['from'];
                $this->lang['to'] = $from;
                $this->lang['from'] = $to;
                $to = $this->to;
                $from = $this->from;
                $this->to = $from;
                $this->from = $to;
            }

            if ($this->display_tofrom === true) {
                if ($this->displayToFromHeaders === true) {
                    $this->Cell($width, $lineheight, mb_strtoupper($this->lang['from'], self::CHARSET_INPUT), 0, 0, 'L');
                    $this->Cell(0, $lineheight, mb_strtoupper($this->lang['to'], self::CHARSET_INPUT), 0, 0, 'L');
                    $this->Ln(7);
                    $this->SetLineWidth(0.4);
                    $this->Line($this->margins['l'], $this->GetY(), $this->margins['l'] + $width - 10, $this->GetY());
                    $this->Line(
                        $this->margins['l'] + $width,
                        $this->GetY(),
                        $this->margins['l'] + $width + $width,
                        $this->GetY()
                    );
                } else {
                    $this->Ln(2);
                }

                //Information
                $this->Ln(5);
                $this->SetTextColor(50, 50, 50);
                $this->SetFont($this->font, 'B', 10);
                $this->Cell($width, $lineheight, $this->from[0] ?? 0, 0, 0, 'L');
                $this->Cell(0, $lineheight, $this->to[0] ?? 0, 0, 0, 'L');
                $this->SetFont($this->font, '', 8);
                $this->SetTextColor(100, 100, 100);
                $this->Ln(7);
                for ($i = 1, $iMax = max($this->from === null ? 0 : count($this->from), $this->to === null ? 0 : count($this->to)); $i < $iMax; $i++) {
                    // avoid undefined error if TO and FROM array lengths are different
                    if (!empty($this->from[$i]) || !empty($this->to[$i])) {
                        $this->Cell($width, $lineheight, empty($this->from[$i]) ? '' : $this->from[$i], 0, 0, 'L');
                        $this->Cell(0, $lineheight, empty($this->to[$i]) ? '' : $this->to[$i], 0, 0, 'L');
                    }
                    $this->Ln(5);
                }
                $this->Ln(-6);
                $this->Ln(5);
            } else {
                $this->Ln(-10);
            }
        }

        $width_other = $this->getOtherColumnsWith();

        //Table header
        if (!isset($this->productsEnded)) {
            $this->SetTextColor(50, 50, 50);
            $this->Ln(12);
            $this->SetFont($this->font, 'B', 9);
            $this->Cell(1, 10, '', 0, 0, 'L', 0);
            $this->Cell(
                $this->firstColumnWidth,
                10,
                mb_strtoupper($this->lang['product'], self::CHARSET_INPUT),
                0,
                0,
                'L',
                0
            );

            if (isset($this->quantityField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['qty'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }
            if (isset($this->priceField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['price'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }
            if (isset($this->discountField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['discount'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }
            if (isset($this->vatField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['vat'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }
            if (isset($this->vatPriceField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['vat'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }

            if (isset($this->totalField)) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->lang['total'], self::CHARSET_INPUT),
                    0,
                    0,
                    'C',
                    0
                );
            }
            $this->Ln();
            $this->SetLineWidth(0.3);
            $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Line($this->margins['l'], $this->GetY(), $this->document['w'] - $this->margins['r'], $this->GetY());
            $this->Ln(2);
        } else {
            $this->Ln(12);
        }

        if ($this->itemsDescription) {
            $this->Cell(
                $width_other,
                10,
                mb_strtoupper($this->itemsDescription, self::CHARSET_INPUT),
                0,
                0,
                'L',
                0
            );
            $this->Ln(12);
        }
    }

    public function Body()
    {
        $width_other = $this->getOtherColumnsWith();
        $cellHeight = 8;
        $bgcolor = (1 - $this->columnOpacity) * 255;
        if ($this->items) {
            foreach ($this->items as $item) {
                if ((empty($item['item'])) || (empty($item['description']))) {
                    $this->Ln($this->columnSpacing);
                }
                if ($item['description']) {
                    //Precalculate height
                    $calculateHeight = new static();
                    $calculateHeight->addPage();
                    $calculateHeight->setXY(0, 0);
                    $calculateHeight->SetFont($this->font, '', 7);
                    $calculateHeight->MultiCell(
                        $this->firstColumnWidth,
                        3,
                        $item['description'],
                        0,
                        'L',
                        0
                    );
                    $descriptionHeight = $calculateHeight->getY() + $cellHeight + 2;
                    $pageHeight = $this->document['h'] - $this->GetY() - $this->margins['t'] - $this->margins['t'] - $descriptionHeight;
                    if ($pageHeight < 1) {
                        $this->AddPage();
                    }
                }
                $cHeight = $cellHeight;
                $this->SetFont($this->font, 'b', 8);
                $this->SetTextColor(50, 50, 50);
                $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                $this->Cell(1, $cHeight, '', 0, 0, 'L', 0);
                $x = $this->GetX();
                $this->Cell(
                    $this->firstColumnWidth,
                    $cHeight,
                    $item['item'],
                    0,
                    0,
                    'L',
                    0
                );
                if ($item['description']) {
                    $resetX = $this->GetX();
                    $resetY = $this->GetY();
                    $this->SetTextColor(120, 120, 120);
                    (empty($item['item'])) ? $this->SetXY($x, $this->GetY() + 3) : $this->SetXY($x, $this->GetY() + 8);
                    $this->SetFont($this->font, '', $this->fontSizeProductDescription);
                    $this->MultiCell(
                        $this->firstColumnWidth,
                        floor($this->fontSizeProductDescription / 2),
                        $item['description'],
                        0,
                        'L',
                        0
                    );
                    //Calculate Height
                    $newY = $this->GetY();
                    $cHeight = $newY - $resetY + 2;
                    //Make our spacer cell the same height
                    $this->SetXY($x - 1, $resetY);
                    $this->Cell(1, $cHeight, '', 0, 0, 'L', 0);
                    //Draw empty cell
                    $this->SetXY($x, $newY);
                    $this->Cell($this->firstColumnWidth, 2, '', 0, 0, 'L', 0);
                    $this->SetXY($resetX, $resetY);
                }
                $this->SetTextColor(50, 50, 50);
                $this->SetFont($this->font, '', 8);

                if (isset($this->quantityField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['quantity'])) {
                        $this->Cell($width_other, $cHeight, $item['quantity'], 0, 0, 'C', 0);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 0);
                    }
                }
                if (isset($this->priceField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['price'])) {
                        $this->Cell($width_other, $cHeight, $item['price'], 0, 0, 'C', 0);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 0);
                    }
                }
                if (isset($this->discountField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['discount'])) {
                        $this->Cell($width_other, $cHeight, $item['discount'], 0, 0, 'C', 1);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 1);
                    }
                }
                if (isset($this->vatField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['vat'])) {
                        $this->Cell($width_other, $cHeight, $item['vat'], 0, 0, 'C', 0);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 0);
                    }
                }
                if (isset($this->vatPriceField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['vatPrice'])) {
                        $this->Cell($width_other, $cHeight, $item['vatPrice'], 0, 0, 'C', 0);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 0);
                    }
                }
                if (isset($this->totalField)) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    if (isset($item['total'])) {
                        $this->Cell($width_other, $cHeight, $item['total'], 0, 0, 'C', 0);
                    } else {
                        $this->Cell($width_other, $cHeight, '', 0, 0, 'C', 0);
                    }
                }
                $this->Ln();
                $this->Ln($this->columnSpacing);
            }
        }
        $badgeX = $this->getX();
        $badgeY = $this->getY();

        //Add totals
        if ($this->totals) {
            if ($this->totalsAlignment == 'horizontal') {
                $this->Ln(2);
                $totalsCount = count($this->totals);
                $cellWidth = ($this->document['w'] - $this->margins['l'] - $this->margins['r']) / $totalsCount;
                // Colors, line width and bold font
                $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
                $this->SetTextColor(255, 255, 255);
                $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
                $this->SetLineWidth(.3);
                $this->SetFont($this->font, 'b', 8);
                // Header
                for ($i=0;$i<$totalsCount;$i++) {
                    $this->Cell(
                        $totalsCount % 2 == 0 ? ($i % 2 == 0 ? $cellWidth + 5 : $cellWidth - 5) : $cellWidth,
                        7,
                        $this->totals[$i]['name'],
                        1,
                        0,
                        'C',
                        0
                    );
                }
                $this->Ln();
                // Values
                $this->SetTextColor(50, 50, 50);
                $this->SetFont($this->font, 'b', 8);
                $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                for ($y=0;$y<$totalsCount;$y++) {
                    $this->Cell(
                        $totalsCount % 2 == 0 ? ($y % 2 == 0 ? $cellWidth + 5 : $cellWidth - 5) : $cellWidth,
                        6,
                        $this->totals[$y]['value'],
                        'LRB',
                        0,
                        'C',
                        $this->totals[$y]['colored']
                    );
                }
                $this->Ln();
            } else {
                foreach ($this->totals as $total) {
                    $this->SetTextColor(50, 50, 50);
                    $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                    $this->Cell(1 + $this->firstColumnWidth, $cellHeight, '', 0, 0, 'L', 0);
                    for ($i = 0; $i < $this->columns - 3; $i++) {
                        $this->Cell($width_other, $cellHeight, '', 0, 0, 'L', 0);
                        $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    }
                    $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    if ($total['colored']) {
                        $this->SetTextColor(255, 255, 255);
                        $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
                    }
                    $this->SetFont($this->font, 'b', 8);
                    $this->Cell(1, $cellHeight, '', 0, 0, 'L', 0);
                    $this->Cell(
                        $width_other - 1,
                        $cellHeight,
                        $total['name'],
                        0,
                        0,
                        'L',
                        0
                    );
                    $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    $this->SetFont($this->font, 'b', 8);
                    $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                    if ($total['colored']) {
                        $this->SetTextColor(255, 255, 255);
                        $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
                    }
                    $this->Cell($width_other, $cellHeight, $total['value'], 0, 0, 'C', 0);
                    $this->Ln();
                    $this->Ln($this->columnSpacing);
                }
            }
        }
        $this->productsEnded = true;
        $this->Ln();
        $this->Ln(3);

        //Badge
        if ($this->badge) {
            $badge = ' ' . mb_strtoupper($this->badge, self::CHARSET_INPUT) . ' ';
            $resetX = $this->getX();
            $resetY = $this->getY();
            $this->setXY($badgeX + 35, $badgeY + ($this->totalsAlignment == 'horizontal' ? 25 : 15));
            $this->SetLineWidth(0.4);
            $this->SetDrawColor($this->badgeColor[0], $this->badgeColor[1], $this->badgeColor[2]);
            $this->setTextColor($this->badgeColor[0], $this->badgeColor[1], $this->badgeColor[2]);
            $this->SetFont($this->font, 'b', 15);
            $this->Rotate(10, $this->getX(), $this->getY());
            $this->Rect($this->GetX(), $this->GetY(), $this->GetStringWidth($badge) + 2, 10);
            $this->Write(10, mb_strtoupper($badge, self::CHARSET_INPUT));
            $this->Rotate(0);
            if ($resetY > $this->getY() + 20) {
                $this->setXY($resetX, $resetY);
            } else {
                $this->Ln(18);
            }
        }

        if ($this->voucherDescription) {
            $this->SetFont($this->font, 'b', 8);
            $this->Cell(
                $width_other,
                10,
                mb_strtoupper($this->voucherDescription, self::CHARSET_INPUT),
                0,
                0,
                'L',
                0
            );

            if ($this->voucherDescription2) {
                $this->Ln(6);
                $this->Cell(
                    $width_other,
                    10,
                    mb_strtoupper($this->voucherDescription2, self::CHARSET_INPUT),
                    0,
                    0,
                    'L',
                    0
                );
            }

            $this->Ln(12);
        }
    }

    public function Footer()
    {
        $this->SetY(-$this->margins['t'] - 13);

        //Add information
        foreach ($this->addText as $text) {
            if ($text[0] == 'title') {
                $this->SetFont($this->font, 'b', 9);
                $this->SetTextColor(50, 50, 50);
                $this->Cell(0, 10, mb_strtoupper($text[1], self::CHARSET_INPUT), 0, 0, 'L', 0);
                $this->Ln();
                $this->SetLineWidth(0.3);
                $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
                $this->Line(
                    $this->margins['l'],
                    $this->GetY(),
                    $this->document['w'] - $this->margins['r'],
                    $this->GetY()
                );
                $this->Ln(4);
            }
            if ($text[0] == 'paragraph') {
                $this->SetTextColor(80, 80, 80);
                $this->SetFont($this->font, '', 8);
                $this->MultiCell(0, 4, $text[1], 0, 'L', 0);
                $this->Ln(4);
            }
        }

        $this->SetFont($this->font, '', 8);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 10, $this->footernote, 0, 0, 'L');
        $this->Cell(
            0,
            10,
            $this->lang['page'] . ' ' . $this->PageNo() . ' ' . $this->lang['page_of'] . ' {nb}',
            0,
            0,
            'R'
        );
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    private function getOtherColumnsWith()
    {
        if ($this->columns === 1) {
            return $this->document['w'] - $this->margins['l'] - $this->margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing);
        }

        return ($this->document['w'] - $this->margins['l'] - $this->margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing))
               / ($this->columns - 1);
    }
}
