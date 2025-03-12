<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Hours</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Project Hours</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Activity Name</th>
                @foreach($personel as $person)
                    <th>{{ $person->Shortname }} (Estimated)</th>
                    <th>{{ $person->Shortname }} (Actual)</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $project)
                <tr>
                    <td colspan="{{ count($personel) * 2 + 1 }}" style="font-weight: bold;">{{ $project->Name }}</td>
                </tr>
                @foreach($activities->where('Project', $project->Id) as $activity)
                    <tr>
                        <td>{{ $activity->Name }}</td>
                        @foreach($personel as $person)
                            @php
                                $personHours = $hours->where('Project', $project->Id)->where('Activity', $activity->Id)->where('Person', $person->Id)->first();
                            @endphp
                            <!-- Planned Hours as Editable Text Field -->
                            <td>
                                <input type="text" class="planned-hours" data-project="{{ $project->Id }}" 
                                       data-activity="{{ $activity->Id }}" data-person="{{ $person->Id }}"
                                       value="{{ $personHours ? $personHours->Plan : '' }}" />
                            </td>
                            <td>{{ $personHours ? $personHours->Hours : '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <script>
        // Send AJAX request when planned hours are changed
        $(document).on('change', '.planned-hours', function() {
            var plannedHours = $(this).val();
            var projectId = $(this).data('project');
            var activityId = $(this).data('activity');
            var personId = $(this).data('person');

            $.ajax({
                url: '/update-planned-hours',
                method: 'POST',
                data: {
                    project_id: projectId,
                    activity_id: activityId,
                    person_id: personId,
                    planned_hours: plannedHours,
                    _token: '{{ csrf_token() }}' // CSRF token for protection
                },
                success: function(response) {
                    alert('Planned hours updated successfully!');
                },
                error: function(xhr, status, error) {
                    alert('An error occurred while updating planned hours.');
                }
            });
        });
    </script>
</body>
</html>

