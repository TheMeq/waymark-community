<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\Testimonial;
use Illuminate\Support\Collection;

final class VisibleTestimonials
{
    public function get(): Collection
    {
        return Testimonial::query()->with('imageMedia')->where('active', true)->orderByDesc('featured')->orderBy('sort_order')->orderBy('id')->get();
    }
}
