@php
    $isEdit = isset($scaffold);
    $tableName = old('table_name', $scaffold->table_name ?? '');
    $modelName = old('model_name', $scaffold->model_name ?? 'App\\Models\\');
    $controllerName = old('controller_name', $scaffold->controller_name ?? 'App\\Admin\\Controllers\\');
    $primaryKey = old('primary_key', $scaffold->primary_key ?? 'id');
    $createOptions = old('create', $scaffold->create_options ?? []);
    $timestamps = old('timestamps', $scaffold->timestamps ?? true);
    $softDeletes = old('soft_deletes', $scaffold->soft_deletes ?? false);
    $fields = old('fields', $scaffold->details->toArray() ?? []);
@endphp

<form method="POST" action="{{ $action }}" id="scaffold" class="needs-validation" autocomplete="off">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="card-body">
        {{-- Table name --}}
        <div class="row mb-3">
            <label class="col-sm-2 control-label">Table name</label>
            <div class="col-sm-4">
                <input type="text" name="table_name" class="form-control" value="{{ $tableName }}" {{ $isEdit ? 'readonly' : '' }} required>
            </div>
        </div>

        {{-- Model --}}
        <div class="row mb-3">
            <label class="col-sm-2 control-label">Model</label>
            <div class="col-sm-4">
                <input type="text" name="model_name" class="form-control" value="{{ $modelName }}">
            </div>
        </div>

        {{-- Controller --}}
        <div class="row mb-3">
            <label class="col-sm-2 control-label">Controller</label>
            <div class="col-sm-4">
                <input type="text" name="controller_name" class="form-control" value="{{ $controllerName }}">
            </div>
        </div>

        {{-- Checkboxes --}}
        <div class="form-row mb-3 offset-sm-2 col-sm-10">
            @foreach(['migration', 'model', 'controller', 'migrate', 'menu_item'] as $option)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="create[]" value="{{ $option }}" id="chk_{{ $option }}"
                            {{ in_array($option, $createOptions) ? 'checked' : '' }}>
                    <label class="form-check-label" for="chk_{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</label>
                </div>
            @endforeach
        </div>

        {{-- Fields --}}
        <hr>
        <h4>Fields <small>(Note: `id` is auto-added)</small></h4>

        <table class="table table-bordered" id="table-fields">
            <thead>
            <tr>
                <th>Order</th>
                <th>Field name</th>
                <th>Type</th>
                <th>Nullable</th>
                <th>Key</th>
                <th>Default</th>
                <th>Comment</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="table-fields-body">
            @foreach($fields as $index => $field)
                <tr>
                    <td><i class="icon-arrows-alt move-handle"></i></td>
                    <td>
                        <input type="text" name="fields[{{ $index }}][name]" class="form-control" value="{{ $field['name'] ?? '' }}">
                    </td>
                    <td>
                        <select name="fields[{{ $index }}][type]" class="form-select">
                            @foreach($dbTypes as $type)
                                <option value="{{ $type }}" {{ ($field['type'] ?? '') == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="checkbox" name="fields[{{ $index }}][nullable]" class="form-check-input"
                                {{ isset($field['nullable']) && $field['nullable'] ? 'checked' : '' }}>
                    </td>
                    <td>
                        <select name="fields[{{ $index }}][key]" class="form-select">
                            <option value="" {{ empty($field['key']) ? 'selected' : '' }}>NULL</option>
                            <option value="unique" {{ ($field['key'] ?? '') === 'unique' ? 'selected' : '' }}>Unique</option>
                            <option value="index" {{ ($field['key'] ?? '') === 'index' ? 'selected' : '' }}>Index</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="fields[{{ $index }}][default]" class="form-control" value="{{ $field['default'] ?? '' }}">
                    </td>
                    <td>
                        <input type="text" name="fields[{{ $index }}][comment]" class="form-control" value="{{ $field['comment'] ?? '' }}">
                    </td>
                    <td>
                        <a class="btn btn-sm btn-danger table-field-remove"><i class="icon-trash"></i> remove</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <a class="btn btn-sm btn-success" id="add-table-field"><i class="icon-plus"></i> Add field</a>

        {{-- Timestamps and Soft Deletes --}}
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="timestamps" name="timestamps" {{ $timestamps ? 'checked' : '' }}>
            <label class="form-check-label" for="timestamps">Timestamps</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="soft_deletes" name="soft_deletes" {{ $softDeletes ? 'checked' : '' }}>
            <label class="form-check-label" for="soft_deletes">Soft Deletes</label>
        </div>

        <div class="form-group mt-3">
            <label>Primary key</label>
            <input type="text" name="primary_key" class="form-control" value="{{ $primaryKey }}">
        </div>

    </div>

    <div class="card-footer">
        <button type="submit" class="btn btn-primary float-end">{{ $isEdit ? 'Update' : 'Submit' }}</button>
    </div>
</form>
