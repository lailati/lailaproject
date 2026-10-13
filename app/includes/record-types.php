<?php
/**
 * One definition per lecturer record type.
 *
 * The admin record browser, the lecturer profile view, Statistics and Reports
 * all read this array, so a column or a search field is described in exactly
 * one place. Adding a new record type means adding one entry here.
 *
 * Keys of 'columns' must match the aliases produced by 'select'.
 * A column may carry:
 *   'format' => 'date' | 'year' | 'badge' | 'number'
 *   'class'  => extra CSS class for the <td>
 *   'badge'  => true renders a coloured pill (status-style values)
 */

function record_types(): array
{
    static $types = null;
    if ($types !== null) {
        return $types;
    }

    $types = [

        'research' => [
            'label'    => 'Research',
            'singular' => 'Research Project',
            'icon'     => 'bi-journal-text',
            'table'    => 'research',
            'select'   => 'x.research_id AS record_id, x.title, x.research_area, x.research_category,
                           x.`year`, x.status, x.updated_at',
            'columns'  => [
                ['key' => 'title',             'label' => 'Research Title'],
                ['key' => 'research_area',     'label' => 'Research Area'],
                ['key' => 'research_category', 'label' => 'Category'],
                ['key' => 'year',              'label' => 'Year',     'format' => 'year',  'class' => 'text-center'],
                ['key' => 'status',            'label' => 'Status',   'format' => 'badge'],
                ['key' => 'updated_at',        'label' => 'Updated',  'format' => 'date'],
            ],
            'search'        => ['x.title', 'x.research_area', 'x.research_category'],
            'status_values' => ['Ongoing', 'Completed'],
            'order'         => 'x.`year` DESC, x.research_id DESC',
        ],

        'publications' => [
            'label'    => 'Publications',
            'singular' => 'publication',
            'icon'     => 'bi-book',
            'table'    => 'publications',
            'select'   => 'x.publication_id AS record_id, x.title, x.publication_type, x.journal_publisher,
                           x.`year`, x.status, x.doi_link, x.updated_at',
            'columns'  => [
                ['key' => 'title',             'label' => 'Publication Title'],
                ['key' => 'publication_type',  'label' => 'Type'],
                ['key' => 'journal_publisher', 'label' => 'Journal / Publisher'],
                ['key' => 'year',              'label' => 'Year',    'format' => 'year', 'class' => 'text-center'],
                ['key' => 'status',            'label' => 'Status',  'format' => 'badge'],
                ['key' => 'doi_link',          'label' => 'DOI / Link'],
            ],
            'search'        => ['x.title', 'x.journal_publisher', 'x.doi_link'],
            'status_values' => ['Published', 'In Press', 'Submitted'],
            'order'         => 'x.`year` DESC, x.publication_id DESC',
        ],

        'certifications' => [
            'label'    => 'Certifications',
            'singular' => 'certification',
            'icon'     => 'bi-patch-check',
            'table'    => 'certifications',
            'select'   => 'x.certification_id AS record_id, x.certification_name, x.issuing_body,
                           x.issue_date, x.expiry_date, x.certificate_number, x.status, x.updated_at',
            'columns'  => [
                ['key' => 'certification_name', 'label' => 'Certification Name'],
                ['key' => 'issuing_body',       'label' => 'Issuing Body'],
                ['key' => 'issue_date',         'label' => 'Issue Date',  'format' => 'date'],
                ['key' => 'expiry_date',        'label' => 'Expiry Date', 'format' => 'date'],
                ['key' => 'status',             'label' => 'Status',      'format' => 'badge'],
            ],
            'search'        => ['x.certification_name', 'x.issuing_body', 'x.certificate_number'],
            'status_values' => ['active', 'expired'],
            'order'         => 'x.expiry_date IS NULL, x.expiry_date ASC, x.certification_id DESC',
        ],

        'training' => [
            'label'    => 'Training',
            'singular' => 'Training Record',
            'icon'     => 'bi-mortarboard',
            'table'    => 'training',
            'select'   => 'x.training_id AS record_id, x.training_name, x.organizer, x.`date`,
                           x.duration_days, x.status',
            'columns'  => [
                ['key' => 'training_name', 'label' => 'Training / Course Name'],
                ['key' => 'organizer',     'label' => 'Organizer'],
                ['key' => 'date',          'label' => 'Date',     'format' => 'date'],
                ['key' => 'duration_days', 'label' => 'Days',     'format' => 'number', 'class' => 'text-center'],
                ['key' => 'status',        'label' => 'Status',   'format' => 'badge'],
            ],
            'search'        => ['x.training_name', 'x.organizer'],
            'status_values' => ['Completed', 'Ongoing', 'Planned'],
            'order'         => 'x.`date` DESC, x.training_id DESC',
        ],

        'consultancy' => [
            'label'    => 'Consultancy',
            'singular' => 'Consultancy Project',
            'icon'     => 'bi-briefcase',
            'table'    => 'consultancy',
            'select'   => 'x.consultancy_id AS record_id, x.project_name, x.organization, x.`role`,
                           x.`year`, x.status',
            'columns'  => [
                ['key' => 'project_name', 'label' => 'Project / Consultancy Name'],
                ['key' => 'organization',  'label' => 'Organization / Client'],
                ['key' => 'role',          'label' => 'Role'],
                ['key' => 'year',          'label' => 'Year',   'format' => 'year', 'class' => 'text-center'],
                ['key' => 'status',        'label' => 'Status', 'format' => 'badge'],
            ],
            'search'        => ['x.project_name', 'x.organization', 'x.role'],
            'status_values' => ['Ongoing', 'Completed'],
            'order'         => 'x.`year` DESC, x.consultancy_id DESC',
        ],

        'achievements' => [
            'label'    => 'Achievements',
            'singular' => 'achievement',
            'icon'     => 'bi-trophy',
            'table'    => 'achievements',
            'select'   => 'x.achievement_id AS record_id, x.achievement_name, x.category, x.`year`',
            'columns'  => [
                ['key' => 'achievement_name', 'label' => 'Achievement'],
                ['key' => 'category',         'label' => 'Category'],
                ['key' => 'year',             'label' => 'Year', 'format' => 'year', 'class' => 'text-center'],
            ],
            'search'        => ['x.achievement_name', 'x.category'],
            'status_values' => null,   // this table has no status column
            'order'         => 'x.`year` DESC, x.achievement_id DESC',
        ],

        'documents' => [
            'label'    => 'Documents',
            'singular' => 'document',
            'icon'     => 'bi-folder2-open',
            'table'    => 'documents',
            'select'   => 'x.document_id AS record_id, x.title, x.document_type, x.file_name,
                           x.file_size, x.upload_date, x.status',
            'columns'  => [
                ['key' => 'title',         'label' => 'Document Name'],
                ['key' => 'document_type', 'label' => 'Type'],
                ['key' => 'file_name',     'label' => 'File'],
                ['key' => 'file_size',     'label' => 'Size',   'format' => 'bytes'],
                ['key' => 'upload_date',   'label' => 'Upload Date', 'format' => 'date'],
                ['key' => 'status',        'label' => 'Status', 'format' => 'badge'],
            ],
            'search'        => ['x.title', 'x.file_name', 'x.document_type'],
            'status_values' => ['active', 'archived'],
            'order'         => 'x.upload_date DESC, x.document_id DESC',
            // documents also record WHO uploaded the file (an admin may upload
            // on a lecturer's behalf), so the insert must set this column.
            'owner_user_column' => 'uploaded_by',
        ],
    ];

    return $types;
}

/** A single type definition, or null when the key is unknown. */
function record_type(?string $key): ?array
{
    $types = record_types();
    return ($key !== null && isset($types[$key])) ? $types[$key] : null;
}

/** All record type keys, in sidebar order. */
function record_type_keys(): array
{
    return array_keys(record_types());
}

/** Render one record cell according to its declared format. */
function record_cell(array $col, array $row): string
{
    $key   = $col['key'];
    $value = $row[$key] ?? null;

    switch ($col['format'] ?? '') {
        case 'date':
            return e(format_date($value));
        case 'year':
            return $value ? e((string)$value) : '—';
        case 'bytes':
            return e(bytes_human($value !== null ? (int)$value : null));
        case 'number':
            return $value === null ? '—' : e((string)$value);
        case 'badge':
            if ($value === null || $value === '') {
                return '—';
            }
            $css = match (strtolower((string)$value)) {
                'ongoing', 'active', 'published', 'completed' => 'text-bg-success',
                'in press', 'planned', 'beginner'             => 'text-bg-info',
                'submitted', 'archived', 'expired'            => 'text-bg-secondary',
                default                                        => 'text-bg-light border text-dark',
            };
            return '<span class="badge ' . $css . '">' . e((string)$value) . '</span>';
        default:
            if ($value === null || $value === '') {
                return '—';
            }
            if ($key === 'doi_link') {
                $url = preg_match('#^https?://#', (string)$value) ? $value : 'https://doi.org/' . $value;
                return '<a href="' . e($url) . '" target="_blank" rel="noopener">'
                     . '<small>' . e((string)$value) . '</small></a>';
            }
            return e((string)$value);
    }
}

/**
 * Primary key column of a record type, e.g. 'research_id'.
 *
 * Derived from the start of the type's select clause ("x.research_id AS record_id"),
 * so every module agrees on the key without repeating it in the definitions.
 */
function record_pk(?string $key): ?string
{
    $type = record_type($key);
    if ($type === null) {
        return null;
    }
    if (!preg_match('/^\s*[A-Za-z_]+\.([A-Za-z_]+)\s+AS\s+record_id/i', $type['select'], $m)) {
        return null;
    }
    return $m[1];
}
