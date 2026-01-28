<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponseTrait;
    private const ALLOWED_TYPES = ['product', 'service', 'ad', 'listing'];
    /**
     * Display a listing of the resource.
     */
    private function validateRequest(Request $request, array $rules)
    {
        try {
            return $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new HttpResponseException(
                $this->errorResponse('validation_failed', 'messages', 422, [
                    'errors' => $e->errors(),
                ])
            );
        }
    }
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->has('type')) {
            if (!in_array($request->type, self::ALLOWED_TYPES, true)) {
                return $this->errorResponse('invalid_category_type', 'messages', 422);
            }
            $query->where('type', $request->type);
        }

        $categories = $query->get();
        return $this->successResponse($categories, 'messages', 'categoriesـretrievedـsuccessfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateRequest($request, [
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'type' => ['nullable', 'string', Rule::in(self::ALLOWED_TYPES)],
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:4096'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = 'storage/' . $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return $this->successResponse($category, 'messages', 'created');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->errorResponse('not_found', 'messages', 404);
        }

        $data = $this->validateRequest($request, [
            'name' => 'sometimes|array',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'type' => ['nullable', 'string', Rule::in(self::ALLOWED_TYPES)],
            'slug' => 'sometimes|string|max:255|unique:categories,slug,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:4096'
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = 'storage/' . $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return $this->successResponse($category, 'messages', 'updated');
    }


    /**
     * Remove the specified resource from storage.
     */

    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->errorResponse('not_found', 'messages', 404);
        }
        $category->delete();
        return $this->successResponse([], 'messages', 'deleted');
    }
}
