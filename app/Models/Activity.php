namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $table = 'Activities';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    public function project()
    {
        return $this->belongsTo(Project::class, 'Project');
    }
}
