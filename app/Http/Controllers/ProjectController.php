namespace App\Http\Controllers;

use App\Models\Hours;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = \App\Models\Project::all();
        $activities = \App\Models\Activity::all();
        $personel = \App\Models\Personel::all();
        $hours = \App\Models\Hours::all();

        return view('projects.index', compact('projects', 'activities', 'personel', 'hours'));
    }

    public function updatePlannedHours(Request $request)
    {
        $request->validate([
            'project_id' => 'required|integer',
            'activity_id' => 'required|integer',
            'person_id' => 'required|integer',
            'planned_hours' => 'required|integer',
        ]);

        // Find the corresponding Hours record
        $hours = Hours::where('Project', $request->project_id)
            ->where('Activity', $request->activity_id)
            ->where('Person', $request->person_id)
            ->first();

        // Update the planned hours if the record exists
        if ($hours) {
            $hours->Plan = $request->planned_hours;
            $hours->save();
            return response()->json(['message' => 'Planned hours updated successfully']);
        }

        // If no record is found, return an error
        return response()->json(['message' => 'Record not found'], 404);
    }
}

