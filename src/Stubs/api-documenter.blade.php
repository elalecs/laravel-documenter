## API: {{ $apiGroupName }}

| {{ implode(' | ', $tableHeaders) }} |
|{{ implode('|', array_fill(0, count($tableHeaders), '---')) }}|
@foreach($tableRows as $row)
| {{ $row['method'] }} | {{ $row['uri'] }} | {{ $row['name'] }} | {{ $row['action'] }} | {{ $row['middleware'] }} |
@endforeach
