<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use app\interfaces\BookingRepositoryInterface;
/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepositoryInterface $bookingRepository
     */

    // Create a BookingRepositoryInterface and make use of it here to implement the polymorphic behaviour of OOP principle.
    // It helps in decoupling the application repositories and you can attach a new repository to the BookingRepositoryInterface when any code changes or upgrade needed in future.
    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobs($user_id);

        } // utilize config variables instead of direct env variables, It can cause an fatal error if env variables not found. While using config variables you can set a default parameter if env variable not exist.
        // Also you can get Authenticated user from current request "$request->user()->user_type" instead of making additional variables in base controller or in traits.
        elseif($request->user()->user_type == config('constants.ADMIN_ROLE_ID') || $request->user()->user_type == config('constants.SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        }
        // You can make use of API Resources to expressively and easily transform your models and model collections into JSON.
        return BookingResource::collection($response);
//        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        // Make use of FindOrFail() function to generate "404 Not Found" if $id does not found.
        $job = $this->repository->with('translatorJobRel.user')->findOrFail($id);

        return new BookingResource($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    // use BookingStoreRequest class to validate the request data for insertion
    // make use of validated() method to store validated data
    public function store(BookingStoreRequest $request)
    {
        $response = $this->repository->store($request->user(), $request->validated());

        return new BookingResource($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    // Here you also have to use UpdateBookingRequest class to validate incoming request
    public function update($id, UpdateBookingRequest $request)
    {
        $response = $this->repository->updateJob($id, array_except($request->validated(), ['_token', 'submit']), $request->user());

        return BookingResource::collection($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        // Remove unused variable from the controller
        // Remove extra variables assignment

        $response = $this->repository->storeJobEmail($request->all());

        return new EmailResource($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return new HistoryResource($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $response = $this->repository->acceptJob($request->all(), $request->user());

        return new JobResource($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $response = $this->repository->acceptJobWithId($request->job_id, $request->user());

        return new JobResource($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $response = $this->repository->cancelJobAjax($request->all(), $request->user());

        return new JobResource($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $response = $this->repository->endJob($request->all());

        return new JobResource($response);

    }

    public function customerNotCall(Request $request)
    {
        $response = $this->repository->customerNotCall($request->all());

        return new CustomerResource($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        // Remove unused variables
        $response = $this->repository->getPotentialJobs($request->user());

        return new JobResource($response);
    }

    public function distanceFeed(Request $request)
    {
        // You can just access distance as magic method and use of coalesce operator.
        // Similarly rest of the code can be optimized

        $distance = $request->distance ?? "";
        $time = $request->time ?? "";
        $jobid = $request->jobid ?? "";
        $session = $request->session_time ?? "";
        $admincomment = $request->admincomment ?? "";
        $manually_handled = $request->manually_handled === 'true' ? 'yes' : 'no';
        $by_admin = $request->by_admin === 'true' ? 'yes' : 'no';

        $flagged = 'no';
        if ($request->flagged === 'true') {
            if($request->admincomment == '') return "Please, add comment";
            $flagged = 'yes';
        }

        if ($time || $distance) {
            // Remove unused variables
            Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            // Remove unused variables
            Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));
        }

        return new DistanceResource('Record updated!');
    }

    public function reopen(Request $request)
    {
        $response = $this->repository->reopen($request->all());

        return new BookingResource($response);
    }

    public function resendNotifications(Request $request)
    {
        $job = $this->repository->find($request->jobid);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response()->json(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->jobid);
        // Remove unused variables
        $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response()->json(['success' => $e->getMessage()]);
        }
    }

}
