<?php
declare(strict_types=1);

function photo_columns(string $kind, string $label, int $count): array
{
    $columns = [];
    for ($index = 1; $index <= $count; $index++) {
        $columns[] = ['type' => 'photo', 'kind' => $kind, 'index' => $index, 'label' => slot_label($label, $index)];
    }
    return $columns;
}

function slot_label(string $label, int $position): string
{
    return $position === 1 ? $label : "$label $position";
}

function text_column(string $label, string $field, bool $right = false): array
{
    return ['type' => 'text', 'label' => $label, 'field' => $field, 'right' => $right];
}

function registers(): array
{
    static $registers = null;
    if ($registers !== null) {
        return $registers;
    }
    $upload = [text_column('Upload Date', 'uploadDate'), text_column('Uploaded By (Employee)', 'uploader')];
    $registers = [
        'SALE' => [
            'type' => 'SALE', 'slug' => 'sale', 'label' => 'Sale', 'number' => 'Invoice No', 'date' => 'Invoice Date',
            'party' => 'Customer', 'amount' => 'Invoice Amount',
            'groups' => [['kind' => 'RECEIPT_COPY', 'label' => 'Receipt Copy', 'max' => 1, 'required' => true]],
            'template' => [['Customer', 'party', true], ['Invoice No', 'no', true], ['Invoice Date', 'date', true], ['Invoice Amount', 'amount', true], ['Receipt', 'receipt', false], ['MR', 'mr', false], ['Area', 'area', false], ['Address', 'address', false]],
            'columns' => array_merge(
                [text_column('Customer', 'party'), ['type' => 'link', 'label' => 'Invoice No'], text_column('Invoice Date', 'date'), text_column('Invoice Amount', 'amount', true), text_column('Receipt', 'receipt'), text_column('MR', 'mr'), text_column('Area', 'area'), text_column('Address', 'address')],
                photo_columns('RECEIPT_COPY', 'Receipt Copy', 1),
                $upload,
                [['type' => 'status', 'label' => 'Status']]
            ),
            'form' => [['photos', 'RECEIPT_COPY'], ['auto', ''],],
        ],
        'CF_SALE' => [
            'type' => 'CF_SALE', 'slug' => 'cf-sales', 'label' => 'C&F Sales', 'number' => 'Invoice No', 'date' => 'Invoice Date',
            'party' => 'Customer', 'amount' => 'Invoice Amount',
            'groups' => [
                ['kind' => 'RECEIPT_COPY', 'label' => 'Receipt Copy', 'max' => 1, 'required' => true],
                ['kind' => 'INVOICE_PHOTO', 'label' => 'Invoice Photo', 'max' => 10, 'required' => true],
            ],
            'template' => [['Invoice No', 'no', true], ['Invoice Date', 'date', true], ['Customer', 'party', true], ['Invoice Amount', 'amount', true]],
            'columns' => array_merge(
                [['type' => 'link', 'label' => 'Invoice No'], text_column('Invoice Date', 'date'), text_column('Customer', 'party'), text_column('Invoice Amount', 'amount', true)],
                photo_columns('RECEIPT_COPY', 'Receipt Copy', 1),
                photo_columns('INVOICE_PHOTO', 'Invoice Photo', 10),
                $upload,
                [['type' => 'status', 'label' => 'Status']]
            ),
            'form' => [['photos', 'RECEIPT_COPY'], ['photos', 'INVOICE_PHOTO'], ['auto', '']],
        ],
        'PURCHASE' => [
            'type' => 'PURCHASE', 'slug' => 'purchase', 'label' => 'Purchase', 'number' => 'Challan No', 'date' => 'Challan Date',
            'party' => 'Supplier', 'amount' => 'Amount',
            'groups' => [
                ['kind' => 'CHALLAN_PHOTO', 'label' => 'Challan Photo', 'max' => 40, 'required' => true],
                ['kind' => 'GOODS_PHOTO', 'label' => 'Goods Photo', 'max' => 40, 'required' => false],
            ],
            'template' => [['Supplier', 'party', true], ['Challan No', 'no', true], ['Challan Date', 'date', true], ['Amount', 'amount', true], ['No of Item', 'items', false], ['MR', 'mr', false], ['Area', 'area', false], ['Address', 'address', false]],
            'columns' => array_merge(
                [text_column('Supplier', 'party'), ['type' => 'link', 'label' => 'Challan No'], text_column('Challan Date', 'date'), text_column('Amount', 'amount', true), text_column('No of Item', 'items'), text_column('MR', 'mr'), text_column('Area', 'area'), text_column('Address', 'address')],
                photo_columns('CHALLAN_PHOTO', 'Challan Photo', 40),
                photo_columns('GOODS_PHOTO', 'Goods Photo', 40),
                $upload,
                [['type' => 'status', 'label' => 'Upload Status'], text_column('Remarks', 'remarks')]
            ),
            'form' => [['photos', 'CHALLAN_PHOTO'], ['photos', 'GOODS_PHOTO'], ['auto', ''], ['remarks', '']],
        ],
    ];
    return $registers;
}

function register_by_slug(string $slug): ?array
{
    foreach (registers() as $register) {
        if ($register['slug'] === $slug) {
            return $register;
        }
    }
    return null;
}

function group_for(array $register, string $kind): ?array
{
    foreach ($register['groups'] as $group) {
        if ($group['kind'] === $kind) {
            return $group;
        }
    }
    return null;
}

function is_complete(array $register, array $counts): bool
{
    foreach ($register['groups'] as $group) {
        if ($group['required'] && ($counts[$group['kind']] ?? 0) < 1) {
            return false;
        }
    }
    return true;
}

function section_icon(string $slug): string
{
    return [
        'sale' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/>',
        'cf-sales' => '<path d="M2 6h12v11H2z"/><path d="M14 10h4l4 4v3h-8z"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="18" cy="18.5" r="1.8"/>',
        'purchase' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/>',
        'masters' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.7 6.5-5.7s5.9 2.1 6.5 5.7"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.9.8 3.1 2.6 3.5 5.2"/>',
    ][$slug];
}

/** Buttons for the main sections. Masters is for Admins only. */
function section_links(array $user): array
{
    $links = [];
    foreach (registers() as $register) {
        $links[] = ['slug' => $register['slug'], 'label' => $register['label'], 'href' => '/r/' . $register['slug'], 'match' => '/r/' . $register['slug']];
    }
    if ($user['role'] === 'ADMIN') {
        $links[] = ['slug' => 'masters', 'label' => 'Masters', 'href' => '/admin/users', 'match' => '/admin'];
    }
    return $links;
}
