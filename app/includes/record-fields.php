<?php
/**
 * Form field definitions for the seven lecturer record types.
 *
 * record-types.php describes how a record is DISPLAYED (table columns);
 * this file describes how it is EDITED (form inputs, validation rules).
 * Keeping them apart means the admin browser columns and the lecturer edit
 * form can each change without disturbing the other, while both still agree
 * on the underlying column names.
 *
 * Field keys:
 *   name     column name in the table
 *   label    form label (Bahasa Melayu, matching the approved mockups)
 *   type     text | textarea | number | date | select | file
 *   required bool
 *   max      maximum length for text/textarea
 *   min,max  bounds for number
 *   options  allowed values for select
 *   col      Bootstrap grid width (1-12)
 *   help     helper text under the input
 *   file     for type=file: which columns the upload writes to
 */

function record_fields(?string $type = null): array
{
    static $fields = null;

    if ($fields === null) {
        $fields = [

            'research' => [
                ['name' => 'title', 'label' => 'Research Title', 'type' => 'text',
                 'required' => true, 'max' => 255, 'col' => 12],
                ['name' => 'research_area', 'label' => 'Research Area', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 6,
                 'help' => 'Contoh: Cybersecurity, Artificial Intelligence'],
                ['name' => 'research_category', 'label' => 'Research Category', 'type' => 'select',
                 'options' => ['Applied Research', 'Basic Research', 'Action Research', 'Experimental Development'],
                 'col' => 6],
                ['name' => 'year', 'label' => 'Year', 'type' => 'number',
                 'min' => 1990, 'max' => 2100, 'col' => 4],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select',
                 'required' => true, 'options' => ['Ongoing', 'Completed'], 'col' => 4],
                ['name' => 'description', 'label' => 'Abstract / Description', 'type' => 'textarea',
                 'max' => 2000, 'col' => 12],
                ['name' => 'supporting_document', 'label' => 'Supporting Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'PDF, JPG atau PNG. Maksimum 5 MB.'],
            ],

            'publications' => [
                ['name' => 'title', 'label' => 'Publication Title', 'type' => 'text',
                 'required' => true, 'max' => 255, 'col' => 12],
                ['name' => 'publication_type', 'label' => 'Publication Type', 'type' => 'select',
                 'required' => true, 'col' => 6,
                 'options' => ['Journal Article', 'Conference Paper', 'Book', 'Book Chapter', 'Patent', 'Technical Report']],
                ['name' => 'journal_publisher', 'label' => 'Journal / Publisher', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 6],
                ['name' => 'year', 'label' => 'Year', 'type' => 'number',
                 'min' => 1990, 'max' => 2100, 'col' => 4],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select',
                 'required' => true, 'options' => ['Published', 'In Press', 'Submitted'], 'col' => 4],
                ['name' => 'doi_link', 'label' => 'DOI / Link', 'type' => 'text',
                 'max' => 255, 'col' => 12, 'help' => 'Contoh: https://doi.org/10.1109/xxxx'],
                ['name' => 'supporting_document', 'label' => 'Upload Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'PDF sahaja disyorkan. Maksimum 5 MB.'],
            ],

            'certifications' => [
                ['name' => 'certification_name', 'label' => 'Certification Name', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 6,
                 'help' => 'Contoh: Certified Information Systems Security Professional (CISSP)'],
                ['name' => 'issuing_body', 'label' => 'Issuing Body', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 6, 'help' => 'Contoh: (ISC)2'],
                ['name' => 'issue_date', 'label' => 'Issue Date', 'type' => 'date',
                 'required' => true, 'col' => 3],
                ['name' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date',
                 'col' => 3, 'help' => 'Biarkan kosong jika tiada tarikh luput.'],
                ['name' => 'certificate_number', 'label' => 'Certificate Number', 'type' => 'text',
                 'max' => 50, 'col' => 6, 'required' => false],
                ['name' => 'supporting_document', 'label' => 'Supporting Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'Salinan sijil. PDF, JPG atau PNG, maksimum 5 MB.'],
            ],

            'training' => [
                ['name' => 'training_name', 'label' => 'Training / Course Name', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 8],
                ['name' => 'organizer', 'label' => 'Organizer', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 4],
                ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4],
                ['name' => 'duration_days', 'label' => 'Duration (days)', 'type' => 'number',
                 'min' => 1, 'max' => 365, 'col' => 4],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select',
                 'required' => true, 'options' => ['Completed', 'Ongoing', 'Planned'], 'col' => 4],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                 'max' => 1000, 'col' => 12],
                ['name' => 'supporting_document', 'label' => 'Supporting Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'Sijil kehadiran / penyertaan. Maksimum 5 MB.'],
            ],

            'consultancy' => [
                ['name' => 'project_name', 'label' => 'Project / Consultancy Name', 'type' => 'text',
                 'required' => true, 'max' => 200, 'col' => 12],
                ['name' => 'organization', 'label' => 'Organization / Client', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 6],
                ['name' => 'role', 'label' => 'Role', 'type' => 'text',
                 'required' => true, 'max' => 100, 'col' => 6, 'help' => 'Contoh: Project Leader'],
                ['name' => 'year', 'label' => 'Year', 'type' => 'number',
                 'min' => 1990, 'max' => 2100, 'col' => 4],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select',
                 'required' => true, 'options' => ['Ongoing', 'Completed'], 'col' => 4],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                 'max' => 1000, 'col' => 12],
                ['name' => 'supporting_document', 'label' => 'Supporting Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'Surat lantikan / laporan. Maksimum 5 MB.'],
            ],

            'achievements' => [
                ['name' => 'achievement_name', 'label' => 'Achievement Name', 'type' => 'text',
                 'required' => true, 'max' => 200, 'col' => 8,
                 'help' => 'Contoh: Best Researcher Award'],
                ['name' => 'category', 'label' => 'Category', 'type' => 'select',
                 'col' => 4, 'options' => ['Research', 'Teaching', 'Service', 'Innovation', 'Others']],
                ['name' => 'year', 'label' => 'Year', 'type' => 'number',
                 'min' => 1990, 'max' => 2100, 'col' => 4],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                 'max' => 1000, 'col' => 12],
                ['name' => 'supporting_document', 'label' => 'Supporting Document', 'type' => 'file',
                 'col' => 6, 'file' => ['path_field' => 'supporting_document'],
                 'help' => 'Sijil anugerah. PDF/JPG/PNG, maksimum 5 MB.'],
            ],

            'documents' => [
                ['name' => 'document_type', 'label' => 'Document Type', 'type' => 'select',
                 'required' => true, 'col' => 4,
                 'options' => ['CV', 'Portfolio', 'Report', 'Certificate', 'Transcript', 'Others']],
                ['name' => 'title', 'label' => 'Title / Description', 'type' => 'text',
                 'required' => true, 'max' => 150, 'col' => 8,
                 'help' => 'Contoh: Latest CV 2024'],
                ['name' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true, 'col' => 8,
                 'help' => 'PDF, JPG atau PNG. Maksimum 10 MB.',
                 'file' => [
                     'name_field' => 'file_name',
                     'path_field' => 'file_path',
                     'size_field' => 'file_size',
                 ]],
            ],
        ];
    }

    if ($type === null) {
        return $fields;
    }
    return $fields[$type] ?? [];
}

/** Fields of one type that actually exist as columns to be written. */
function writable_fields(string $type): array
{
    $out = [];
    foreach (record_fields($type) as $field) {
        if ($field['type'] === 'file') {
            continue;   // handled by the uploader, not by direct assignment
        }
        $out[] = $field;
    }
    return $out;
}
