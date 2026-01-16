import InsuranceStatusDetail from './InsuranceStatusDetail';
import { formatDate } from './formatters';

/**
 * Insurance compliance card showing all insurance statuses
 */
export default function InsuranceComplianceCard({ vendor, insuranceStatus }) {
    return (
        <div className="card">
            <div className="card-header">
                <h2 className="text-lg font-medium text-gray-900">Insurance Compliance</h2>
            </div>
            <div className="card-body space-y-3">
                <InsuranceStatusDetail
                    status={insuranceStatus?.workers_comp}
                    date={vendor.workers_comp_expires}
                    label="Workers Comp"
                />
                <InsuranceStatusDetail
                    status={insuranceStatus?.liability}
                    date={vendor.liability_ins_expires}
                    label="Liability Insurance"
                />
                <InsuranceStatusDetail
                    status={insuranceStatus?.auto}
                    date={vendor.auto_ins_expires}
                    label="Auto Insurance"
                />
                {vendor.state_lic_expires && (
                    <div className="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                        <span className="font-medium text-gray-700">State License</span>
                        <span className="text-sm text-gray-600">
                            {formatDate(vendor.state_lic_expires)}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}
