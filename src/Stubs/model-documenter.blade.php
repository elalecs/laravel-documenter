## Model: {{ $modelName }}

{{ $description }}

**Table:** {{ $tableName }}

@if($fillable)
**Fillable Attributes:**
@foreach($fillable as $attribute)
- {{ $attribute }}
@endforeach
@endif

@if($relationships)
**Relationships:**
@foreach($relationships as $relationship)
- {{ $relationship->name }}: {{ $relationship->type }} with {{ $relationship->relatedModel }}
@endforeach
@endif

@if($scopes)
**Scopes:**
@foreach($scopes as $scope)
- {{ $scope['name'] }}: {{ $scope['description'] }}
@endforeach
@endif

@if($casts)
**Attribute Casts:**
@foreach($casts as $cast)
- {{ $cast['attribute'] }}: {{ $cast['type'] }}
@endforeach
@endif
