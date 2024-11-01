import withAlertDialog from "../../../AlertDialog";

const Export = ({
  status,
  setIsOpen,
  results,
  updateSettings,
  settings,
  setSettings,
}) => {
  const handleCheckboxChange = () => {
    const newExportSyncedValue = settings.exportSynced === 0 ? 1 : 0;
    setSettings({ ...settings, exportSynced: newExportSyncedValue });
    updateSettings("exportSynced", newExportSyncedValue);
  };

  return (
    <div className="px-4 py-5 sm:p-6">
      <h3 className="text-base font-semibold leading-6 text-gray-900">
        Export products to Square 
      </h3>
      <div className="mt-2 max-w-xl text-sm text-gray-500">
        <p>
          Have an existing Woo store but an empty Square catalog? Use this
          feature to export your Woo products to Square and have them
          automatically linked. (Creates new products on Square)
        </p>
      </div>
      {status === 0 ? (
        <div className="flex flex-col items-start gap-2 mt-4">
          <label className="relative inline-flex items-center cursor-pointer justify-start">
            <input
              type="checkbox"
              checked={settings.exportSynced === 1}
              onChange={handleCheckboxChange}
              className="sr-only peer"
            />
            <div className="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
            <span className="ms-3 text-sm font-medium text-gray-700 ">
              Enable to export already linked products
            </span>
          </label>
          <button
            type="button"
            onClick={() => setIsOpen(true)}
            className="bg-sky-500 text-white rounded-lg px-4 py-2 font-bold mt-2"
          >
            Export products to Square
          </button>
        </div>
      ) : (
        <div className="flex items-center mt-2">
          <svg
            className="animate-spin -ml-1 mr-3 h-5 w-5 text-sky-500"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            ></circle>
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          Export is currently running in the background, please come back later
          to see results.
        </div>
      )}
      {results && results.success && status !== 1 && (
        <div>
          <p className="text-sm text-gray-500 mb-1">Export results:</p>
          <div className="bg-gray-800 p-4 text-white rounded-lg max-h-[300px] overflow-y-auto overflow-x-hidden nowrap w-10/12">
            <ul>
              {results.success.map((batch, index) => (
                <li key={index}>
                  {batch.success ? (
                    batch.data.objects.map((group, i) => (
                      <li key={i} className="text-green-500">
                        success: Product {group.item_data.name} created in
                        Square
                      </li>
                    ))
                  ) : (
                    <li className="text-red-500">failed: {batch.error}</li>
                  )}
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
};

const ExportProducts = withAlertDialog(Export);
export default ExportProducts;
