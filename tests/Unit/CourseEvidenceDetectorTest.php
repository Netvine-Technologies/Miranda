<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\CourseEvidenceDetector;
use PHPUnit\Framework\TestCase;

class CourseEvidenceDetectorTest extends TestCase
{
    public function test_it_requires_both_the_course_subject_and_training_language(): void
    {
        $detector = new CourseEvidenceDetector();

        $this->assertTrue($detector->matches('<h1>Accredited lash extension training course</h1>', 'lash'));
        $this->assertTrue($detector->matches('<h1>Nail technician courses</h1>', 'nail'));
        $this->assertFalse($detector->matches('<h1>Nail salon</h1><p>Book a manicure today</p>', 'nail'));
        $this->assertFalse($detector->matches('<h1>Hairdressing academy</h1><p>We also offer nail treatments</p>', 'nail'));
        $this->assertFalse($detector->matches('<script>lash training courses</script><p>Lash extensions</p>', 'lash'));
    }
}
