## {{ $apiGroupName }}

@foreach($endpoints as $endpoint)
### {{ $endpoint->method }} {{ $endpoint->path }}

**Handler:** {{ $endpoint->handlerClass }}@{{ $endpoint->handlerMethod }}

{{ $endpoint->description }}

@if($endpoint->parameters)
**Parameters:**
@foreach($endpoint->parameters as $parameter)
- {{ $parameter->name }} ({{ $parameter->type }}): {{ $parameter->description }}
@endforeach
@endif

@if($endpoint->middleware)
**Middleware:**
@foreach($endpoint->middleware as $middleware)
- {{ $middleware }}
@endforeach
@endif

**Response:**
```json
{!! $endpoint->responseExample !!}
```

@endforeach