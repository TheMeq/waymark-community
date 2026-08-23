<?php

use App\Domain\Communication\Models\ContactDepartment;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Governance\Actions\PublishDocumentVersion;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Domain\Governance\Models\DocumentVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$administrator = User::query()->where('email', 'media.admin@example.test')->firstOrFail();

CmsPage::query()->create([
    'title' => 'Walking with us',
    'slug' => 'walking-with-us',
    'publication_state' => 'published',
    'publish_at' => CarbonImmutable::parse('2026-08-19 09:00:00'),
    'blocks' => [
        ['type' => 'rich_text', 'content' => '<p>Friendly walks, practical information and plenty of good company.</p>'],
        ['type' => 'callout', 'heading' => 'Before you set out', 'body' => 'Check the route, weather and meeting point before travelling.'],
        ['type' => 'faq', 'items' => [['question' => 'Do I need walking boots?', 'answer' => 'Most countryside walks need supportive footwear with good grip.']]],
        ['type' => 'quote', 'quote' => 'I came for a walk and found a community.', 'attribution' => 'A local walker'],
        ['type' => 'cta', 'heading' => 'Find your next walk', 'body' => 'Choose a route and pace that suits you.', 'label' => 'Upcoming walks', 'url' => '/walks'],
        ['type' => 'statistics', 'heading' => 'At a glance', 'items' => [['value' => 'Weekly', 'label' => 'new walks'], ['value' => 'Friendly', 'label' => 'local community']]],
        ['type' => 'timeline', 'heading' => 'Your first walk', 'items' => [['label' => 'Choose', 'body' => 'Read the grade and practical details.'], ['label' => 'Meet', 'body' => 'Arrive at the published meeting point.']]],
        ['type' => 'columns', 'heading' => 'Good to know', 'items' => [['label' => 'Weather', 'body' => 'Bring layers and waterproofs.'], ['label' => 'Food', 'body' => 'Carry enough water and lunch.']]],
    ],
]);

NewsArticle::query()->create([
    'title' => 'Paths, people and late-summer plans',
    'slug' => 'paths-people-late-summer-plans',
    'summary' => 'A concise update from the walking community.',
    'blocks' => [
        ['type' => 'rich_text', 'content' => '<p>Our late-summer programme brings together local paths, longer days out and a welcoming mix of paces.</p>'],
        ['type' => 'callout', 'heading' => 'Dates for your diary', 'body' => 'New walks are published throughout the month.'],
    ],
    'author_id' => $administrator->id,
    'primary_category' => 'Community',
    'tags' => ['walks', 'community'],
    'publication_state' => 'published',
    'publish_at' => CarbonImmutable::parse('2026-08-19 10:00:00'),
    'featured_on_homepage' => true,
]);

$category = DocumentCategory::query()->create(['name' => 'Walking guidance', 'slug' => 'walking-guidance', 'sort_order' => 10]);
$document = Document::query()->create([
    'document_category_id' => $category->id,
    'title' => 'Walking guide',
    'slug' => 'walking-guide',
    'description' => 'Practical information for joining a group walk.',
    'visibility' => 'public',
    'publication_date' => '2026-08-19',
    'public_version_history' => true,
    'download_count' => 0,
    'controlled' => false,
    'approval_status' => 'approved',
]);
$path = 'documents/3f2504e0-4f89-41d3-9a0c-0305e82c3399/v1.pdf';
Storage::disk('local')->put($path, '%PDF-1.4 browser fixture');
$version = DocumentVersion::query()->create([
    'document_id' => $document->id,
    'version_number' => 1,
    'storage_disk' => 'local',
    'storage_path' => $path,
    'original_filename' => 'walking-guide.pdf',
    'mime_type' => 'application/pdf',
    'file_size_bytes' => 24,
    'created_by_user_id' => $administrator->id,
]);
app(PublishDocumentVersion::class)->handle($administrator, $document, $version);

ContactDepartment::query()->create([
    'public_label' => 'Walk programme',
    'destination_email' => 'walks@example.test',
    'description' => 'Questions about upcoming walks, grades and meeting points.',
    'show_address_publicly' => true,
    'active' => true,
    'sort_order' => 10,
]);
ContactDepartment::query()->create([
    'public_label' => 'General enquiries',
    'destination_email' => 'private@example.test',
    'description' => 'Membership, volunteering and other questions.',
    'show_address_publicly' => false,
    'active' => true,
    'sort_order' => 20,
]);
