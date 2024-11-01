import { Link } from "react-router-dom";

export default function NextCron({ cron }) {
  return (
    <section className=" bg-white rounded-xl p-4 w-full">
      <header className="flex flex-col items-start justify-start gap-2 relative w-full">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          strokeWidth={1.5}
          stroke="#000"
          className="w-6 h-6"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
          />
        </svg>
        <h3 className="text-base font-semibold leading-6 text-gray-900">
          Automatic scheduler is {cron && cron.status ? 'on' : 'off'}
        </h3>
        {cron && cron.status ?
          <div className="absolute top-1 right-0">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div> :
          <div className="absolute top-1 right-0">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
          </div>

        }

      </header>
      {cron && cron.status ? <div className="mt-2">
        <p className="text-gray-500 ">
          The next automatic data sync to{" "}
          <span className="text-sky-500">{cron.direction}</span> will occur:{" "}
          <br></br>
          <span className="text-sky-500">{cron.next_run}</span>
          {cron.time_until_next_run.length > 0 && (
            <>
              ,{" "}
              <span className="text-sky-500">({cron.time_until_next_run})</span>
            </>
          )}
        </p>
        <p className="mt-3 text-gray-500 ">The following data will be synced:</p>
        <p className="mt-px text-gray-500 ">
          {Object.keys(cron["data_to_import"])
            .filter((key) => cron["data_to_import"][key])
            .map((key, idx, filteredKeys) => (
              <span key={key}>
                <span className="text-sky-500">{key}</span>
                {idx !== filteredKeys.length - 1 ? ", " : ""}
              </span>
            ))}
        </p>
      </div> : <p className="mt-2 text-gray-500 ">Automatic sheduler is currently disabled. To enable, go to inventory settings <Link to={'/settings/inventory'} className="text-sky-500">here</Link>.</p>}

    </section>
  );
}
