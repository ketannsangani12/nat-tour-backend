<?php

namespace App\Repositories\Addon;

use App\Repositories\Repository;
use App\Models\AddonForm;
use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonFieldRule;
use App\Models\AddonFieldRuleChildren;
use App\Models\AddonCategory;
use App\Models\TourPackage;
use App\Models\TourBookingAddonField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AddonFormRepository extends Repository
{
    /**
	 * @var string
	 */
	public $addonForm = AddonForm::class;

	public $addonField = AddonField::class;

	public $addonFieldOption = AddonFieldOption::class;

	public $addonFieldRule = AddonFieldRule::class;

	public $addonFieldRuleChildren = AddonFieldRuleChildren::class;

	public $addonCategory = AddonCategory::class;

	public $tourPackage = TourPackage::class;

    /**
	 * Get the package addon form from the db by tour package id
	 * @method get
	 * @param $tourPackageId
     * @param $builder [1 – Fetch inactive/active forms in the add-on builder during package edit, 0 – Fetch active form add-ons for booking details or booking creation]
     * @param bool $forFrontend Whether the caller is the customer-facing site (tour-frontend, via
     *        API V2) rather than an admin/staff/affiliate booking screen. Both are reached with
     *        $builder=0, but they need OPPOSITE visibility filtering (Section below) — the
     *        booking-vs-builder distinction alone can't tell them apart.
     *
	 */
	public function getPackageAddonForm($tourPackageId, $builder, bool $forFrontend = false)
    {
        try {
            $getForm = $this->addonForm::where('tour_package_id', $tourPackageId)->limit(1)->first();

            if(!$getForm) {
                return response()->json([
                    "success" => true,
                    "tour_package_id" => $tourPackageId,
                    "form" => null,
                    "error" => null,
                ]);
            }

            $form = $this->getAddonForm($tourPackageId, $getForm->id, $builder, $forFrontend);

            // Only to use on create booking page
            if ($form) {
                $topFields = collect($form->fields);

                // A price_per_pax field flagged display_once is still charged per traveler
                // (see AddonBookingService::insertPricePerPaxBookingAddons) — only where it's
                // rendered on the booking page changes: it joins the general "fields" bucket
                // instead of being duplicated once per traveler in "pax_fields".
                $paxFields = $topFields->filter(fn($f) => strtolower($f['unit_type']) === 'price_per_pax' && empty($f['display_once']))->values();
                $otherFields = $topFields->filter(fn($f) => strtolower($f['unit_type']) !== 'price_per_pax' || !empty($f['display_once']))->values();

                // Add to form->addons
                $form->addons = new \stdClass();
                $form->addons->pax_fields = $paxFields;
                $form->addons->fields = $otherFields;
            }

            return response()->json([
                "success" => true,
                "tour_package_id" => $tourPackageId,
                "form" => $form,
                "error" => null,
            ]);

        } catch (Exception $exception) {
            Log::error($exception);

            return response()->json([
                "success" => false,
                "tour_package_id" => $tourPackageId,
                "form" => null,
                "error" => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Return the fully hydrated form with nested fields (n-level).
     */
    public function getAddonForm($tourPackageId, $formId, $builder, bool $forFrontend = false)
    {
        $form = $this->addonForm::where('tour_package_id', $tourPackageId)
            ->when(!$builder, fn($q) => $q->where('status', 'Active'))
            ->when($formId, fn($q) => $q->where('id', $formId))
            ->first();

        if (!$form) return null;

        // A field marked 'frontend' should show ONLY on the customer-facing site;
        // 'backend' ONLY on admin/staff/affiliate booking screens; 'both' on either. Both kinds
        // of non-builder caller reach this method with $builder=0, so $forFrontend (set
        // explicitly by the API V2 controller tour-frontend calls) is what actually picks the
        // right side of that pair — without it, a 'frontend'-only field was silently dropped
        // before ever reaching tour-frontend, since this always applied the admin-side pair.
        $visibleTo = $forFrontend ? ['both', 'frontend'] : ['both', 'backend'];

       // Fetch parent fields
        $parentFields = $this->addonField::where('form_id', $form->id)
            ->whereNull('deleted_at')
            ->whereNull('parent_field_id')
            ->when(!$builder, function ($q) use ($visibleTo) {
                $q->where('status', 'Active')
                ->whereIn('visibility', $visibleTo);
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        // Fetch all rule-linked child fields, scoped to this form's own rules —
        // otherwise another package's null-parent rule-child can leak into this tree
        $ruleChildIds = $this->addonFieldRuleChildren::whereHas('rule.field', function ($q) use ($form) {
                $q->where('form_id', $form->id);
            })
            ->pluck('child_field_id')
            ->unique();
        $ruleChildrenFields = $this->addonField::whereIn('id', $ruleChildIds)
            ->whereNull('deleted_at')
            ->get();

        // Merge parent + child fields
        $fields = $parentFields->concat($ruleChildrenFields)->keyBy('id');

        // Load all options
        $options = $this->addonFieldOption::whereIn('field_id', $fields->keys())
            ->orderBy('sort_order', 'asc')
            ->get()
            ->groupBy('field_id');

        // Load all rules
        $rules = $this->addonFieldRule::whereIn('field_id', $fields->keys())
            ->whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('field_id');

        // Load all rule->children pivot
        $ruleChildren = $this->addonFieldRuleChildren::whereIn('rule_id', $rules->flatten()->pluck('id'))
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('rule_id');

        // Attach options + rules + children relationships
        foreach ($fields as $field) {
            $fieldOptions = $options->get($field->id, collect());
            $field->options = $fieldOptions;
            $field->rules = $rules->get($field->id, collect())->map(function($rule) use ($ruleChildren, $fields, $fieldOptions) {
                $ruleArr = $rule->toArray();

                // Attach option reference (already bulk-loaded above — a rule's
                // option_id always belongs to its own field, so no extra query needed)
                $ruleArr['option'] = $rule->option_id
                    ? $fieldOptions->firstWhere('id', $rule->option_id)?->toArray()
                    : null;

                // Attach children fields recursively (just ids for now)
                $childIds = $ruleChildren->get($rule->id, collect())->pluck('child_field_id');
                $ruleArr['children'] = $childIds
                    ->map(fn($id) => $fields->get($id))
                    ->filter()
                    ->sortBy('sort_order', SORT_NUMERIC)
                    ->values();

                return $ruleArr;
            });
        }

        // Bulk-load category names once (small lookup table) to avoid N+1 while resolving category_name below
        $categoryNames = $this->addonCategory::pluck('name', 'id');
        $packageNames = $this->tourPackage::pluck('name', 'id');

        // $buildTree = function ($field) use (&$buildTree, $fields) {
        $buildTree = function ($field, $parentUnitType = null, $parentCategoryId = null, $parentPackageId = null) use (&$buildTree, $fields, $categoryNames, $packageNames) {
            $node = $field->toArray();

            // Determine unit_type: field, else inherit parent, else null
            $node['unit_type'] = $field->unit_type ?: $parentUnitType;
            $node['options'] = $field->options->toArray();

            // Determine category: field, else inherit parent, else null
            $node['category_id'] = $field->category_id ?: $parentCategoryId;
            $node['category_name'] = $categoryNames->get($node['category_id']);

            // Determine linked package: field, else inherit parent, else null
            $node['package_id'] = $field->package_id ?: $parentPackageId;
            $node['package_name'] = $packageNames->get($node['package_id']);

            $currentUnitType = $node['unit_type'];
            $currentCategoryId = $node['category_id'];
            $currentPackageId = $node['package_id'];

            // Recursively build rules children
            $node['rules'] = [];
            foreach ($field->rules as $rule) {
                $ruleArr = $rule;
                $ruleArr['children'] = collect($rule['children'])
                    // ->map(fn($child) => $buildTree($child))
                    ->map(fn($child) => $buildTree($child, $currentUnitType, $currentCategoryId, $currentPackageId))
                    ->sortBy('sort_order', SORT_NUMERIC)
                    ->values()
                    ->toArray();
                $node['rules'][] = $ruleArr;
            }

            // Recursively build direct children
            $children = $fields->where('parent_field_id', $field->id)
                ->sortBy('sort_order', SORT_NUMERIC)
                ->values();

            $node['children'] = $children
                ->map(fn($c) => $buildTree($c, $currentUnitType, $currentCategoryId, $currentPackageId))
                // ->map(fn($c) => $buildTree($c))
                ->values()
                ->toArray();

            return $node;
        };

        // Recursive sorter for n-level sorting
        $sortChildrenRecursively = function ($children) use (&$sortChildrenRecursively) {
            return collect($children)
                ->map(function ($child) use (&$sortChildrenRecursively) {
                    if (!empty($child['rules'])) {
                        foreach ($child['rules'] as &$rule) {
                            if (!empty($rule['children'])) {
                                $rule['children'] = $sortChildrenRecursively($rule['children']);
                            }
                        }
                    }

                    if (!empty($child['children'])) {
                        $child['children'] = $sortChildrenRecursively($child['children']);
                    }

                    return $child;
                })
                ->sortBy('sort_order', SORT_NUMERIC)
                ->values()
                ->toArray();
        };

        // Build top-level fields tree from $parentFields directly — not $fields.
        // $fields also holds rule-declared children, which can have a null
        // parent_field_id (a data bug) and must never be treated as tree roots.
        $tree = $parentFields
            ->map(fn($f) => $buildTree($f, $f->unit_type, $f->category_id, $f->package_id))
            ->sortBy('sort_order', SORT_NUMERIC)
            ->values()
            ->toArray();

        // Apply recursive sort
        $form->fields = $sortChildrenRecursively($tree);

        return $form;
    }

    /**
     * Save (create/update) entire addon form with N-level fields.
     */
    public function saveAddonForm(Request $request)
    {
        DB::beginTransaction();
        try {
            $action = $request->input('id') ? 'update' : 'insert';

            // Optional safety: handle explicit deleted IDs first (frontend can send)
            $deletedIds = $request->input('deleted_field_ids', []);

            // Create / update form
            $form = $this->addonForm::updateOrCreate(
                ['id' => $request->input('id')],
                $request->only(['tour_package_id','name','description','status'])
            );

            // processed trackers used to cleanup DB rows not present in payload
            $processed = [
                'fields'  => [],
                'options' => [],
                'rules'   => [],
                'pivots'  => []
            ];

            // Soft delete any explicit deleted ids (optional safety net)
            if (!empty($deletedIds)) {
                foreach ($deletedIds as $did) {
                    $f = $this->addonField::find($did);
                    if ($f) $this->softDeleteFieldRecursively($f);
                }
            }

            // Process incoming top-level fields (parent_field_id = null)
            $incomingFields = $request->input('fields', []);
            foreach ($incomingFields as $f) {
                $this->saveFieldRecursive($form, $f, null, $processed);
            }

            // Cleanup DB rows not referenced in processed arrays
            $this->cleanupUnprocessed($form, $processed);

            DB::commit();

            // Return fresh tree (fully recursive)
            $updatedForm = $this->getAddonForm($form->tour_package_id, $form->id, 1);

            return response()->json([
                'message' => 'Form saved successfully',
                'action'  => $action,
                'form'    => $updatedForm
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return response()->json(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    /**
     * Soft-delete a field and all its descendants (rules,pivots,options,children).
     */
    private function softDeleteFieldRecursively(AddonField $field)
    {
        if (!$field || $field->trashed()) return;

        // Delete rules & pivots (and recursively orphaned children)
        foreach ($field->rules()->get() as $rule) {
            $pivots = $this->addonFieldRuleChildren::where('rule_id', $rule->id)->get();
            foreach ($pivots as $pivot) {
                $pivot->delete();
                // after deleting pivot, if child field becomes orphaned -> delete it recursively
                $cf = $this->addonField::find($pivot->child_field_id);
                if ($cf && $this->addonFieldRuleChildren::where('child_field_id', $cf->id)->count() === 0) {
                    $this->softDeleteFieldRecursively($cf);
                }
            }
            $rule->delete();
        }

        // Delete options of this field
        foreach ($field->options()->get() as $opt) {
            $opt->delete();
        }

        // Delete children that were stored using parent_field_id (if any)
        foreach ($field->children()->get() as $child) {
            $this->softDeleteFieldRecursively($child);
        }

        // Finally soft-delete the field itself
        $field->delete();
    }

    /**
     * Recursively save/update a field and its subtree.
     * $parentFieldId = parent_field_id (nullable)
     */
    private function saveFieldRecursive(AddonForm $form, array $fieldData, $parentFieldId = null, array &$processed)
    {
        // Upsert field
        $field = $this->addonField::updateOrCreate(
            ['id' => $fieldData['id'] ?? null],
            [
                'form_id' => $form->id,
                'parent_field_id' => $parentFieldId,
                'label' => $fieldData['label'] ?? null,
                'type' => $fieldData['type'] ?? null,
                'description' => $fieldData['description'] ?? null,
                'required' => $fieldData['required'] ?? false,
                'unit_type' => $fieldData['unit_type'] ?? null,
                'category_id' => $fieldData['category_id'] ?? null,
                'package_id' => $fieldData['package_id'] ?? null,
                'visibility' => $fieldData['visibility'] ?? null,
                'status' => $fieldData['status'] ?? null,
                'additional_fee' => $fieldData['additional_fee'] ?? false,
                'display_once' => $fieldData['display_once'] ?? false,
                'price' => $fieldData['price'] ?? null,
                'min' => $fieldData['min'] ?? null,
                'max' => $fieldData['max'] ?? null,
                'sort_order' => $fieldData['sort_order'] ?? 0,
            ]
        );

        // Mark processed field id
        $processed['fields'][] = $field->id;

        // ----------------
        // OPTIONS
        // ----------------
        $optionMap = []; // value => id mapping
        $incomingOptionIds = collect($fieldData['options'] ?? [])->pluck('id')->filter()->toArray();

        foreach ($fieldData['options'] ?? [] as $opt) {
            $option = $this->addonFieldOption::updateOrCreate(
                ['id' => $opt['id'] ?? null],
                [
                    'field_id' => $field->id,
                    'label' => $opt['label'] ?? null,
                    'value' => $opt['value'] ?? null,
                    'additional_fee' => $opt['additional_fee'] ?? false,
                    'price' => $opt['price'] ?? null,
                    'sort_order' => $opt['sort_order'] ?? 0,
                ]
            );
            $processed['options'][] = $option->id;
            $optionMap[$opt['value']] = $option->id;
        }

        // ----------------
        // RULES & PIVOTS & CHILDREN
        // ----------------
        $incomingRuleIds = collect($fieldData['rules'] ?? [])->pluck('id')->filter()->toArray();

        // Find and remove (soft delete) rules that are no longer present
        $rulesToRemove = $field->rules()->whereNotIn('id', $incomingRuleIds)->get();
        foreach ($rulesToRemove as $r) {
            // delete pivot rows first
            $this->addonFieldRuleChildren::where('rule_id', $r->id)->get()->each->delete();
            // delete rule
            $r->delete();
        }

        // Process incoming rules
        foreach ($fieldData['rules'] ?? [] as $ruleData) {
            $optionId = null;

            // Only map option_id for radio/dropdown with 'equals' operator
            if (isset($ruleData['operator']) && in_array($ruleData['operator'], ['equals']) && isset($ruleData['value'])) {
                $optionId = $optionMap[$ruleData['value']] ?? null;
            }
            
            $rule = $this->addonFieldRule::updateOrCreate(
                ['id' => $ruleData['id'] ?? null],
                [
                    'field_id' => $field->id,
                    // 'option_id' => $ruleData['option_id'] ?? null,
                    'option_id' => $optionId,
                    'operator' => $ruleData['operator'] ?? null,
                    'value' => $ruleData['value'] ?? null,
                ]
            );

            $processed['rules'][] = $rule->id;

            // Manage pivot children: removed children -> delete pivot(s) then orphan-check child field
            $incomingChildIds = collect($ruleData['children'] ?? [])->pluck('id')->filter()->toArray();

            $existingPivotChildIds = $this->addonFieldRuleChildren::where('rule_id', $rule->id)
                ->pluck('child_field_id')
                ->toArray();

            $removedChildIds = array_diff($existingPivotChildIds, $incomingChildIds);
            if (!empty($removedChildIds)) {
                foreach ($removedChildIds as $rmChildId) {
                    // delete pivot(s)
                    $this->addonFieldRuleChildren::where('rule_id', $rule->id)
                        ->where('child_field_id', $rmChildId)
                        ->get()
                        ->each
                        ->delete();

                    // re-check whether that child is referenced anywhere else as pivot
                    $remaining = $this->addonFieldRuleChildren::where('child_field_id', $rmChildId)->count();
                    if ($remaining === 0) {
                        $cf = $this->addonField::find($rmChildId);
                        if ($cf) $this->softDeleteFieldRecursively($cf);
                    }
                }
            }

            // Save incoming children recursively and create/update pivot entries
            foreach ($ruleData['children'] ?? [] as $childData) {
                $childField = $this->saveFieldRecursive($form, $childData, $field->id, $processed);

                // ensure pivot row exists (create or restore)
                $pivot = $this->addonFieldRuleChildren::withTrashed()
                    ->updateOrCreate(
                        ['rule_id' => $rule->id, 'child_field_id' => $childField->id],
                        []
                    );
                if ($pivot->trashed()) $pivot->restore();

                $processed['pivots'][] = $pivot->id;
            }
        }

        return $field;
    }

    /**
     * Cleanup DB rows for this form that were NOT processed in current payload.
     * Soft-delete them safely (respecting pivot references).
     */
    private function cleanupUnprocessed(AddonForm $form, array $processed)
    {
        // Deduplicate lists
        $processed = array_map(function($a){ return array_values(array_unique($a)); }, $processed);

        // 1) RULES not processed -> delete (and their pivots)
        $rulesToDelete = $this->addonFieldRule::whereHas('field', function($q) use ($form){
            $q->where('form_id', $form->id);
        })->whereNotIn('id', $processed['rules'])->get();

        foreach ($rulesToDelete as $rule) {
            $this->addonFieldRuleChildren::where('rule_id', $rule->id)->get()->each->delete();
            $rule->delete();
        }

        // 2) OPTIONS not processed -> delete
        $this->addonFieldOption::whereHas('field', function($q) use ($form){
            $q->where('form_id', $form->id);
        })->whereNotIn('id', $processed['options'])->get()->each->delete();

        // 3) PIVOTS not processed -> delete
        $allPivotIds = $this->addonFieldRuleChildren::whereHas('rule.field', function($q) use ($form){
            $q->where('form_id', $form->id);
        })->pluck('id')->toArray();

        $pivotsToDelete = array_diff($allPivotIds, $processed['pivots'] ?? []);
        if (!empty($pivotsToDelete)) {
            $this->addonFieldRuleChildren::whereIn('id', $pivotsToDelete)->get()->each->delete();
        }

        // 4) FIELDS not processed -> delete (only if not referenced by any pivot)
        $fieldsMaybeDelete = $this->addonField::where('form_id', $form->id)
            ->whereNotIn('id', $processed['fields'])
            ->get();

        foreach ($fieldsMaybeDelete as $f) {
            // skip if this field is referenced by any pivot as a child
            if ($this->addonFieldRuleChildren::where('child_field_id', $f->id)->exists()) {
                continue;
            }
            // otherwise safe to soft-delete recursively
            $this->softDeleteFieldRecursively($f);
        }
    }

    /**
	 * Hard Delete The Addon Form
	 * Has no cascade effect
	 * @param $formId primary row id
	 * @param $tourPackageId package id
	 */
    public function deleteAddonForm($formId)
    {
        DB::beginTransaction();
        try {
            $form = $this->addonForm::findOrFail($formId);
            $form->delete();

            DB::commit();
			return response()->json(["success" => true,  "id" => $formId, "error" => null]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return response()->json(["success" => false, "id" => $formId, "error" => $exception]);
		}
    }

    /**
     * List active addon categories (for the builder's Category dropdown).
     */
    public function getAddonCategories()
    {
        try {
            $categories = $this->addonCategory::where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(["success" => true, "categories" => $categories, "error" => null]);
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json(["success" => false, "categories" => [], "error" => $exception->getMessage()], 500);
        }
    }

    /**
     * List packages eligible to be linked as an add-on (for the builder's
     * Package dropdown) — only packages explicitly flagged "Include In
     * Add-ons" and Active, excluding the package currently being edited so a
     * package can never link back to itself.
     */
    public function getAddonPackages($tourPackageId = null)
    {
        try {
            $packages = $this->tourPackage::where('include_in_addons', 1)
                ->where('status', 'Active')
                ->when($tourPackageId, fn($q) => $q->where('id', '!=', $tourPackageId))
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(["success" => true, "packages" => $packages, "error" => null]);
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json(["success" => false, "packages" => [], "error" => $exception->getMessage()], 500);
        }
    }

    /**
     * Find-or-create an addon category by name (case-insensitive, trimmed).
     * Used by the builder's "create new category" flow so retyping an existing
     * name (with different casing/whitespace) never creates a duplicate row.
     */
    public function saveAddonCategory(string $name)
    {
        try {
            $name = trim($name);

            $category = $this->addonCategory::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

            if (!$category) {
                $nextSortOrder = ((int) $this->addonCategory::max('sort_order')) + 10;

                $category = $this->addonCategory::create([
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'status' => 'Active',
                    'sort_order' => $nextSortOrder,
                ]);
            }

            return response()->json([
                "success" => true,
                "category" => $category->only(['id', 'name']),
                "error" => null,
            ], 201);
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json(["success" => false, "category" => null, "error" => $exception->getMessage()], 500);
        }
    }
}