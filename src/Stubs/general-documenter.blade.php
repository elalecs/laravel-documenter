## {{ $sectionName }}

@foreach($classes as $class)
### {{ $class->namespace }}\{{ $class->className }}

{{ $class->description }}

@if($class->traits)
**Traits:**
@foreach($class->traits as $trait)
- {{ $trait }}
@endforeach
@endif

@if($class->properties)
**Properties:**
@foreach($class->properties as $property)
- {{ $property->accessType }} {{ $property->name }}: {{ $property->type }}
  {{ $property->description }}
@endforeach
@endif

**Methods:**
@foreach($class->methods as $method)
- {{ $method->accessType }} {{ $method->name }}({{ $method->parameters }}): {{ $method->returnType }}
  {{ $method->description }}
@endforeach

@endforeach