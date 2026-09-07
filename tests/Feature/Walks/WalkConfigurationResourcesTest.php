<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\GradeResource;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\WalkFieldSettingsResource;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;
use Tests\TestCase;

final class WalkConfigurationResourcesTest extends TestCase
{
    public function test_configuration_resources_expose_only_the_supported_admin_fields(): void
    {
        $this->assertSame(Grade::class, GradeResource::getModel());
        $this->assertSame(Tag::class, TagResource::getModel());
        $this->assertSame(WalkFieldSettings::class, WalkFieldSettingsResource::getModel());

        $this->assertSame(
            ['display_order', 'name', 'description', 'colour'],
            $this->fieldNames(GradeResource::form(Schema::make())),
        );
        $this->assertSame(
            ['display_order', 'name', 'description', 'colour'],
            array_map(static fn (Field $field): string => $field->getName(), GradeResource::creationFormComponents()),
        );
        $this->assertSame(
            ['name', 'description', 'colour'],
            array_map(static fn (Field $field): string => $field->getName(), GradeResource::creationFormComponents(false)),
        );
        $this->assertSame(['name'], $this->fieldNames(TagResource::form(Schema::make())));
        $this->assertSame(
            ['name'],
            array_map(static fn (Field $field): string => $field->getName(), TagResource::creationFormComponents()),
        );
        $this->assertSame(
            ['field_configuration', 'leaders_can_publish_directly'],
            $this->fieldNames(WalkFieldSettingsResource::form(Schema::make())),
        );
    }

    /** @return array<int, string> */
    private function fieldNames(Schema $schema): array
    {
        return array_map(
            static fn (Field $field): string => $field->getName(),
            $schema->getComponents(),
        );
    }
}
