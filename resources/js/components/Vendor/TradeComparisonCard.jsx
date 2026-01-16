/**
 * Trade comparison rankings card
 */
export default function TradeComparisonCard({ tradeAnalysis }) {
    if (!tradeAnalysis?.primary_trade) {
        return null;
    }

    const RankingBox = ({ ranking, label }) => {
        if (!ranking) return null;

        return (
            <div className="text-center p-3 bg-gray-50 rounded-lg">
                <p className="text-2xl font-semibold text-gray-900">
                    #{ranking.rank}
                </p>
                <p className="text-xs text-gray-500">
                    of {ranking.total} vendors
                </p>
                <p className="text-sm font-medium text-gray-700 mt-1">{label}</p>
            </div>
        );
    };

    return (
        <div className="card lg:col-span-2">
            <div className="card-header">
                <h2 className="text-lg font-medium text-gray-900">
                    Trade Comparison: {tradeAnalysis.primary_trade}
                </h2>
            </div>
            <div className="card-body">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <RankingBox ranking={tradeAnalysis.rankings?.work_order_count} label="Work Orders" />
                    <RankingBox ranking={tradeAnalysis.rankings?.total_spend} label="Total Spend" />
                    <RankingBox ranking={tradeAnalysis.rankings?.avg_cost} label="Avg Cost" />
                    <RankingBox ranking={tradeAnalysis.rankings?.avg_completion_time} label="Speed" />
                </div>
            </div>
        </div>
    );
}
