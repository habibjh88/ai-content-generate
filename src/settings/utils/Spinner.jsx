import {LoadingOutlined} from '@ant-design/icons';
import {Spin} from 'antd';

const antIcon = (
    <LoadingOutlined
        style={{
            fontSize: 24,
        }}
        spin
    />
);

const Spinner = ( props ) => {
    return <Spin className={props?.className} size="small" indicator={antIcon}/>
};

export default Spinner;