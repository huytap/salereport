<?php

/** CSV mapping shared by the legacy upload template and Excel downloads. */
class ContactCsv
{
    public static function columns()
    {
        return array('company', 'biz_scope', 'account_type', 'managed_by', 'building',
            'address_1', 'address_2', 'city', 'country', 'tel', 'fax', 'cell_phone', 'sal',
            'first_name', 'last_name', 'position', 'dob', 'email', 'remarks', 'hotel_produced',
            'fit_series', 'validity_from', 'validity_to', 'rate_code', 'remark_for_rate',
            'market', 'dmc', 'comment', 'website', 'source', 'address_3');
    }

    public static function headers()
    {
        return array('Company', 'Biz Scope', 'Account type', 'Managed by', 'Building',
            'Address 1', 'Address 2', 'City', 'Country', 'Tel', 'Fax', 'Cell Phone', 'Sal',
            'First Name', 'Last Name', 'Position', 'DOB', 'Email', 'Remarks', 'Hotel Produced',
            'Fit / Series', 'Validity From', 'Validity Until', 'Rate Code', 'Remark For Rate',
            'Market', 'DMC', 'Comments', 'Website', 'Source', 'Address 3');
    }

    private static function headerKey($value)
    {
        $value = str_replace(array("\xEF\xBB\xBF", '\\r', '\\n'), '', $value);
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value));
    }

    public static function importStream($stream)
    {
        CustomerPolicy::requireLegacyWrite('contact','import');
        $header = fgetcsv($stream);
        if (!$header) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }
        $mapping = array();
        foreach (self::columns() as $index => $attribute) {
            $mapping[self::headerKey(self::headers()[$index])] = $attribute;
            $mapping[self::headerKey($attribute)] = $attribute;
        }
        $mapping['remark'] = 'remarks';
        $mapping['validityto'] = 'validity_to';
        $columns = array();
        foreach ($header as $value) {
            $key = self::headerKey($value);
            if (!isset($mapping[$key])) {
                throw new InvalidArgumentException('Unknown CSV column: '.$value);
            }
            $columns[] = $mapping[$key];
        }
        if (!in_array('company', $columns) || count($columns) !== count(array_unique($columns))) {
            throw new InvalidArgumentException('The CSV must contain Company and must not repeat columns.');
        }
        $result = array('imported' => 0, 'errors' => array());
        $line = 1;
        while (($row = fgetcsv($stream)) !== false) {
            $line++;
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            if (count($row) !== count($columns)) {
                $result['errors'][] = 'Row '.$line.': expected '.count($columns).' columns.';
                continue;
            }
            $contact = new Contact('create');
            $contact->attributes = array_combine($columns, array_map('trim', $row));
            if ($contact->save()) {
                $result['imported']++;
            } else {
                $messages = array();
                foreach ($contact->getErrors() as $errors) {
                    $messages = array_merge($messages, $errors);
                }
                $result['errors'][] = 'Row '.$line.': '.implode(' ', $messages);
            }
        }
        return $result;
    }
}
