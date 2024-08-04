## Filament Resource: {{ $resourceName }}

Description: {{ $description }}

**Model:** {{ $modelClass }}

@if(!empty($pages))
**Pages:**
@foreach($pages as $page)
- {{ $page }}
@endforeach
@else
No pages found.
@endif

@if(!empty($tableColumns))
**Table Columns:**
@foreach($tableColumns as $column)
- {{ $column }}
@endforeach
@else
No table columns found.
@endif

@if(!empty($formFields))
**Form Fields:**
@foreach($formFields as $field)
- {{ $field['name'] }} ({{ $field['type'] }})
@endforeach
@else
No form fields found.
@endif

@if(!empty($actions))
**Actions:**
@foreach($actions as $action)
- {{ $action }}
@endforeach
@else
No actions found.
@endif

@if(!empty($filters))
**Filters:**
@foreach($filters as $filter)
- {{ $filter }}
@endforeach
@else
No filters found.
@endif

Debug Info:
Pages: {{ json_encode($pages) }}
Table Columns: {{ json_encode($tableColumns) }}
Form Fields: {{ json_encode($formFields) }}
Actions: {{ json_encode($actions) }}
Filters: {{ json_encode($filters) }}