<?php

/** Minimal OOXML workbook: exports contact values as text without the legacy PHPExcel runtime. */
class ContactExcel
{
    private static function xml($value)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function row($number, $values)
    {
        $xml = '<row r="'.$number.'">';
        foreach (array_values($values) as $index => $value) {
            $column = '';
            for ($n = $index + 1; $n > 0; $n = (int)(($n - 1) / 26)) {
                $column = chr(65 + (($n - 1) % 26)).$column;
            }
            $xml .= '<c r="'.$column.$number.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
        }
        return $xml.'</row>';
    }

    public static function write($path, $contacts)
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The Excel workbook could not be created.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Contacts" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .self::row(1, ContactCsv::headers());
            $number = 2;
            foreach ($contacts as $contact) {
                $values = array();
                foreach (ContactCsv::columns() as $attribute) {
                    $values[] = $contact->$attribute;
                }
                $sheet .= self::row($number++, $values);
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>');
        } finally {
            $zip->close();
        }
    }
}
