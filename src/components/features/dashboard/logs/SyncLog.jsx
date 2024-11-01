import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import moment from 'moment';
import {
	CheckCircleIcon,
	ChevronDownIcon,
	ExclamationCircleIcon,
	InformationCircleIcon,
} from '@heroicons/react/20/solid';

import { ArrowsRightLeftIcon } from '@heroicons/react/24/outline';

const SyncLog = () => {
	const [logs, setLogs] = useState([]);

	useEffect(() => {
		const getLogs = async () => {
			try {
				const response = await apiFetch({
					path: '/sws/v1/logs',
					method: 'GET',
				});
	
				if (response instanceof Error || response.status === 401) {
					console.error('Error fetching logs:', response.message);
					return;
				}
				console.log(response)
				if (response.logs) {
					let logsMap = {};
	
					response.logs.forEach(log => {
						let context = log.context;
						if (context && context.parent_id) {
							let parentProcessId = context.parent_id;
							if (!logsMap[parentProcessId]) {
								logsMap[parentProcessId] = { children: [] };
							}
							logsMap[parentProcessId].children.push(log);
						} else {
							const processId = context.process_id || log.id;
							if (!logsMap[processId]) {
								logsMap[processId] = { ...log, children: [] };
							} else {
								logsMap[processId] = { ...log, children: logsMap[processId].children };
							}
						}
					});
	
					const logsWithChildren = Object.values(logsMap)
						.filter(log => log.id)
						.map(log => ({
							...log,
							children: log.children.sort((a, b) => moment(b.timestamp).valueOf() - moment(a.timestamp).valueOf())
						}))
						.sort((a, b) => moment(b.timestamp).valueOf() - moment(a.timestamp).valueOf());
	
					setLogs(logsWithChildren);
				}
			} catch (error) {
				console.error('Failed to fetch logs:', error);
			}
		};
		getLogs();
	}, []);

	const isValid = (jsonString) => {
		try {
			JSON.parse(jsonString);
			return true;
		} catch (e) {
			return false;
		}
	};

	const LogItem = ({ log, isSummary, isChild }) => (
		<div className={`relative pb-4 ${isSummary ? 'flex justify-between items-center' : ''}`}>
			{log.id !== logs[logs.length - 1].id && !isChild ? (
				<span className="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true" />
			) : null}
			<div className={`flex items-start space-x-3 ${isChild && 'ml-10'}`}>
				<div>
					<div className="relative px-1">
						<div className="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
							{log.log_level === 'success' ? (
								<CheckCircleIcon className="h-5 w-5 text-green-500" aria-hidden="true" />
							) : log.log_level === 'error' || log.log_level === 'failed' ? (
								<ExclamationCircleIcon className="h-5 w-5 text-red-500" aria-hidden="true" />
							) : (
								<InformationCircleIcon className="h-5 w-5 text-blue-500" aria-hidden="true" />
							)}
						</div>
					</div>
				</div>
				<div className="min-w-0 flex-1">
					<p className="text-sm text-gray-500 whitespace-nowrap">
						{moment(log.timestamp).format('MMM D h:mma')}
					</p>
					<p>{log.message}</p>
				</div>
				{isSummary && <ChevronDownIcon className="h-5 w-5 text-gray-400" />}
			</div>
		</div>
	);


	return (
		<>
			<div className=" bg-white rounded-xl p-5 w-full">
				<h3 className="text-base font-semibold text-gray-900 mb-6 flex justify-start items-center gap-2">
					<ArrowsRightLeftIcon className="w-6 h-6" />
					Sync Feed
					<span className="text-xs text-gray-500 font-normal mt-[1px] -ml-1">
						{' '}
						- Shows last 1000 logs
					</span>
				</h3>
				{logs.length < 1 && <p>No data, starting import/syncing to view logs</p>}
				<ul role="list" className="overflow-auto max-h-[1042px] h-auto overflow-y-auto">
                    {logs.map((log, idx) => (
                        <li key={log.id || `parent-${idx}`}>
                            {log.children && log.children.length > 0 ? (
                                <details open className='log-details'>
                                    <summary className='list-none'>
                                        <LogItem log={log} isChild={false} isSummary />
                                    </summary>
                                    {log.children.map((child) => (
                                        <LogItem key={child.id} log={child} isChild={true} />
                                    ))}
                                </details>
                            ) : (
                                <LogItem log={log} isChild={false} />
                            )}
                        </li>
                    ))}
                </ul>
			</div>
		</>
	);
};

export default SyncLog;
