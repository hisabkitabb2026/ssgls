<?php

namespace App\Http\Controllers\Company\Settings;

use App\Http\Controllers\Controller;
use App\Support\Pdf\ImageUtils;
use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomTemplateController extends Controller
{
    /**
     * Document types that support custom templates.
     */
    private const DOCUMENT_TYPES = ['invoice1', 'invoice2', 'invoice3', 'lr_receipt', 'lorry_receipt', 'office_invoice'];

    /**
     * List all custom templates for a document type.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $documentType = $request->get('document_type');

        if (! $documentType || ! in_array($documentType, self::DOCUMENT_TYPES)) {
            return response()->json([
                'customTemplates' => [],
                'builtinTemplates' => [],
            ]);
        }

        // Get all formatted templates (built-in + custom) for this type
        $allTemplates = PdfTemplateUtils::getFormattedTemplates('invoice');

        // Filter to only templates matching this document type
        $customTemplates = [];
        $builtinTemplates = [];

        foreach ($allTemplates as $template) {
            if ($template['name'] === $documentType || str_starts_with($template['name'], $documentType)) {
                if ($template['custom']) {
                    $customTemplates[] = $template;
                } else {
                    $builtinTemplates[] = $template;
                }
            }
        }

        // Also list any custom templates that don't match built-in names
        // (user-uploaded templates with custom names)
        $customFiles = Storage::disk('pdf_templates')->files('/invoice');
        foreach ($customFiles as $file) {
            if (! Str::endsWith($file, '.blade.php')) {
                continue;
            }

            $templateName = Str::before(basename($file), '.blade.php');

            // Skip if already in the list
            $exists = false;
            foreach ($customTemplates as $t) {
                if ($t['name'] === $templateName) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                // Check if this template name starts with the document type
                // or if it's a user-uploaded template (doesn't match any built-in)
                $isBuiltinName = in_array($templateName, self::DOCUMENT_TYPES);

                if (! $isBuiltinName) {
                    // User-uploaded custom template — include it
                    $imagePath = PdfTemplateUtils::getCustomTemplateFilePath('invoice', "{$templateName}.png");
                    $imageValue = file_exists($imagePath)
                        ? ImageUtils::toBase64Src($imagePath)
                        : '';

                    $customTemplates[] = [
                        'name' => $templateName,
                        'path' => $imageValue,
                        'custom' => true,
                    ];
                }
            }
        }

        return response()->json([
            'customTemplates' => $customTemplates,
            'builtinTemplates' => $builtinTemplates,
        ]);
    }

    /**
     * Download the source code of a template (built-in or custom).
     *
     * @param  string  $templateName
     * @return BinaryFileResponse|JsonResponse
     */
    public function download($templateName)
    {
        // First check custom template
        $customPath = Storage::disk('pdf_templates')->path("/invoice/{$templateName}.blade.php");

        if (file_exists($customPath)) {
            return response()->download($customPath, "{$templateName}.blade.php");
        }

        // Then check built-in template
        $builtinPath = resource_path("views/app/pdf/invoice/{$templateName}.blade.php");

        if (file_exists($builtinPath)) {
            return response()->download($builtinPath, "{$templateName}.blade.php");
        }

        return response()->json([
            'error' => 'Template not found.',
        ], 404);
    }

    /**
     * Download the built-in template source code for a document type.
     *
     * @param  string  $documentType
     * @return BinaryFileResponse|JsonResponse
     */
    public function downloadBuiltin($documentType)
    {
        $builtinPath = resource_path("views/app/pdf/invoice/{$documentType}.blade.php");

        if (file_exists($builtinPath)) {
            return response()->download($builtinPath, "{$documentType}.blade.php");
        }

        return response()->json([
            'error' => 'Built-in template not found.',
        ], 404);
    }

    /**
     * Upload a new custom template.
     *
     * @return JsonResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:php,blade.php|max:5120',
            'document_type' => 'required|string|in:'.implode(',', self::DOCUMENT_TYPES),
        ]);

        $name = $request->input('name');
        $slug = Str::slug($name, '_');

        // Ensure the slug doesn't conflict with built-in template names
        if (in_array($slug, self::DOCUMENT_TYPES)) {
            return response()->json([
                'error' => 'This name conflicts with a built-in template name. Please choose a different name.',
            ], 422);
        }

        // Check if a custom template with this name already exists
        if (Storage::disk('pdf_templates')->exists("/invoice/{$slug}.blade.php")) {
            return response()->json([
                'error' => 'A template with this name already exists. Please choose a different name.',
            ], 422);
        }

        $file = $request->file('file');
        $contents = file_get_contents($file->getRealPath());

        // Save the template file
        PdfTemplateUtils::toCustomTemplateMarkupFile($contents, 'invoice', $slug);

        return response()->json([
            'message' => 'Template uploaded successfully.',
            'template' => [
                'name' => $slug,
                'custom' => true,
            ],
        ]);
    }

    /**
     * Delete a custom template.
     *
     * @param  string  $templateName
     * @return JsonResponse
     */
    public function destroy($templateName)
    {
        // Prevent deletion of built-in templates
        if (in_array($templateName, self::DOCUMENT_TYPES)) {
            return response()->json([
                'error' => 'Built-in templates cannot be deleted.',
            ], 422);
        }

        $templatePath = "/invoice/{$templateName}.blade.php";

        if (! Storage::disk('pdf_templates')->exists($templatePath)) {
            return response()->json([
                'error' => 'Custom template not found.',
            ], 404);
        }

        Storage::disk('pdf_templates')->delete($templatePath);

        // Also delete the thumbnail if it exists
        $thumbnailPath = "/invoice/{$templateName}.png";
        if (Storage::disk('pdf_templates')->exists($thumbnailPath)) {
            Storage::disk('pdf_templates')->delete($thumbnailPath);
        }

        return response()->json([
            'message' => 'Template deleted successfully.',
        ]);
    }
}
