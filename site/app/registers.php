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
                ['kind' => 'GOODS_PHOTO', 'label' => 'Goods Photo', 'max' => 40, 'required' => true],
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
        'EXPIRY' => [
            'type' => 'EXPIRY', 'slug' => 'expiry', 'label' => 'Expiry', 'number' => 'Expiry Challan No', 'date' => 'Date',
            'party' => 'Customer', 'amount' => 'Amount',
            'groups' => [['kind' => 'EXPIRY_PHOTO', 'label' => 'Expiry Challan Photo', 'max' => 1, 'required' => true]],
            'template' => [['Expiry Challan No', 'no', true], ['Customer', 'party', true], ['Amount', 'amount', true], ['Date', 'date', true]],
            'columns' => array_merge(
                [['type' => 'link', 'label' => 'Expiry Challan No'], text_column('Customer', 'party'), text_column('Amount', 'amount', true), text_column('Date', 'date')],
                photo_columns('EXPIRY_PHOTO', 'Expiry Challan Photo', 1),
                [text_column('Expiry Challan No', 'referenceNo'), text_column('Remarks', 'remarks'), text_column('Receipt Date', 'receiptDate'), text_column('Received By', 'receivedBy')]
            ),
            'form' => [['photos', 'EXPIRY_PHOTO'], ['expiry', '']],
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
        'expiry' => '<path d="M6 3h12M6 21h12"/><path d="M8 3v4l4 5-4 5v4M16 3v4l-4 5 4 5v4"/>',
        'purchase-in' => '<path d="M12 3v11M7 10l5 5 5-5"/><path d="M3 15v5h18v-5"/>',
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
    $links[] = ['slug' => 'purchase-in', 'label' => 'Purchase In', 'href' => '/purchase-in/add', 'match' => '/purchase-in'];
    if ($user['role'] === 'ADMIN') {
        $links[] = ['slug' => 'masters', 'label' => 'Masters', 'href' => '/admin/users', 'match' => '/admin'];
    }
    return $links;
}

/** Left menu for the section being viewed, or null where no section applies. */
/** Group By choices for a section's table. */
function group_labels(array $register): array
{
    return match ($register['type']) {
        'CF_SALE', 'EXPIRY' => ['date' => 'Date', 'customer' => 'Customer', 'none' => 'No Group'],
        'PURCHASE' => ['date' => 'Date', 'area' => 'Area', 'mr' => 'MR', 'supplier' => 'Supplier', 'none' => 'No Group'],
        default => ['date' => 'Date', 'area' => 'Area', 'mr' => 'MR', 'none' => 'No Group'],
    };
}

function group_options(array $register): array
{
    return array_keys(group_labels($register));
}

/** Line icons for the left menu, drawn in the section's colour. */
function menu_icon(string $label): string
{
    $paths = [
        'Home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'Master' => '<path d="M4 5h16v14H4z"/><path d="M4 10h16M10 10v9"/>',
        'Pending' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'Uploaded' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'Reports' => '<path d="M5 20V10M12 20V4M19 20v-7"/>',
        'Bulk upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'Users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.7 6.5-5.7s5.9 2.1 6.5 5.7"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.9.8 3.1 2.6 3.5 5.2"/>',
        'Parties' => '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M9 8h1M14 8h1M9 12h1M14 12h1"/>',
        'Areas' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'Add' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'Pending Challan' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'Settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'Log out' => '<path d="M14 4h6v16h-6"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$label] . '</svg>';
}

function sidebar_for(string $path, array $user): ?array
{
    if ($path === '/purchase-in' || str_starts_with($path, '/purchase-in/')) {
        $items = [
            ['label' => 'Add', 'href' => '/purchase-in/add', 'current' => $path === '/purchase-in/add'],
            ['label' => 'Pending Challan', 'href' => '/purchase-in/pending', 'current' => $path === '/purchase-in/pending'],
            ['label' => 'Master', 'href' => '/purchase-in/master', 'current' => $path === '/purchase-in/master'],
        ];
        if ($user['role'] !== 'UPLOADER') {
            $items[] = ['label' => 'Settings', 'href' => '/purchase-in/settings', 'current' => $path === '/purchase-in/settings'];
        }
        return ['title' => 'Purchase In', 'section' => 'purchase-in', 'items' => $items];
    }
    if (str_starts_with($path, '/admin')) {
        return ['title' => 'Masters', 'section' => 'masters', 'items' => [
            ['label' => 'Users', 'href' => '/admin/users', 'current' => str_starts_with($path, '/admin/users')],
            ['label' => 'Parties', 'href' => '/admin/parties', 'current' => $path === '/admin/parties'],
            ['label' => 'Areas', 'href' => '/admin/areas', 'current' => $path === '/admin/areas'],
        ]];
    }
    $slug = null;
    if (preg_match('#^/r/([a-z-]+)#', $path, $m)) {
        $slug = $m[1];
    } elseif ($path === '/bulk' || $path === '/reports') {
        $slug = (string) ($_GET['type'] ?? 'sale');
    }
    $register = $slug === null ? null : register_by_slug($slug);
    if (!$register) {
        return null;
    }
    $list = '/r/' . $register['slug'];
    $status = (string) ($_GET['status'] ?? '');
    $onList = $path === $list;
    $items = [
        ['label' => 'Master', 'href' => $list, 'current' => $onList && $status === ''],
        ['label' => 'Pending', 'href' => $list . '?status=PENDING', 'current' => $onList && $status === 'PENDING'],
        ['label' => 'Uploaded', 'href' => $list . '?status=UPLOADED', 'current' => $onList && $status === 'UPLOADED'],
    ];
    if ($user['role'] !== 'UPLOADER') {
        $items[] = ['label' => 'Reports', 'href' => '/reports?type=' . $register['slug'], 'current' => $path === '/reports'];
        $items[] = ['label' => 'Bulk upload', 'href' => '/bulk?type=' . $register['slug'], 'current' => $path === '/bulk'];
    }
    return ['title' => $register['label'], 'section' => $register['slug'], 'items' => $items];
}
