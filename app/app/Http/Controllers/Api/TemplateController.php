<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TemplateResource;
use App\Models\Product;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Довідники (лише читання): шаблони і товари.
 */
class TemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TemplateResource::collection(Template::query()->where('is_active', true)->orderBy('id')->get());
    }

    public function show(Template $template): TemplateResource
    {
        return new TemplateResource($template);
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->orderBy('id');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return ProductResource::collection($query->paginate(min((int) $request->query('per_page', 50), 200)));
    }
}
