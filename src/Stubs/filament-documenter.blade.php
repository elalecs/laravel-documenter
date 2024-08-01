## Filament Resource: {{ $resourceName }}

{{ $description }}

**Model:** {{ $modelClass }}

@if($pages)
**Pages:**
@foreach($pages as $page)
- {{ $page->name }}: {{ $page->class }}
@endforeach
@endif

**Table Columns:**
@foreach($tableColumns as $column)
- {{ $column->name }}: {{ $column->component }}
  {{ $column->description }}
@endforeach

**Form Fields:**
@foreach($formFields as $field)
- {{ $field->name }}: {{ $field->component }}
  {{ $field->description }}
@endforeach

@if($actions)
**Actions:**
@foreach($actions as $action)
- {{ $action->name }}: {{ $action->class }}
  {{ $action->description }}
@endforeach
@endif

@if($filters)
**Filters:**
@foreach($filters as $filter)
- {{ $filter->name }}: {{ $filter->class }}
  {{ $filter->description }}
@endforeach
@endif